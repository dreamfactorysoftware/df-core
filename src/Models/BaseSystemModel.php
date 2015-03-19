<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Models;

use DB;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\BadRequestException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class BaseSystemModel
 *
 * @package DreamFactory\Rave\Models
 */
class BaseSystemModel extends BaseModel
{
    protected static $pk = 'id';

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkCreate( $records, $params = [ ] )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }

        $singleRow = ( 1 === count( $records ) ) ? true : false;
        $response = array();
        $transaction = false;
        $errors = array();
        $rollback = ArrayUtils::getBool( $params, 'rollback' );
        $continue = ArrayUtils::getBool( $params, 'continue' );

        try
        {
            //	Start a transaction
            if ( !$singleRow && $rollback )
            {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ( $records as $key => $record )
            {
                try
                {
                    $response[$key] = static::createInternal( $record, $params );
                }
                catch ( \Exception $ex )
                {
                    if ( $singleRow )
                    {
                        throw $ex;
                    }

                    if ( $rollback && $transaction )
                    {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if ( !$continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }

        if ( !empty( $errors ) )
        {
            $msg = array( 'errors' => $errors, 'record' => $response );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $msg );
        }

        //	Commit
        if ( $transaction )
        {
            try
            {
                DB::commit();
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return $singleRow ? current( $response ) : array( 'record' => $response );
    }

    public static function updateById( $id, $record, $params = [ ] )
    {
        ArrayUtils::set( $record, static::$pk, $id );

        return static::bulkUpdate( array( $record ), $params );
    }

    public static function updateByIds( $ids, $record, $params = [ ] )
    {
        if ( !is_array( $ids ) )
        {
            $ids = explode( ",", $ids );
        }

        $records = [ ];

        foreach ( $ids as $id )
        {
            ArrayUtils::set( $record, static::$pk, $id );
            $records[] = $record;
        }

        return static::bulkUpdate( $records, $params );
    }

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkUpdate( $records, $params = [ ] )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There is no record in the request.' );
        }

        $response = array();
        $transaction = null;
        $errors = array();
        $singleRow = ( 1 === count( $records ) ) ? true : false;
        $rollback = ArrayUtils::getBool( $params, 'rollback' );
        $continue = ArrayUtils::getBool( $params, 'continue' );

        try
        {
            //	Start a transaction
            if ( !$singleRow && $rollback )
            {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ( $records as $key => $record )
            {
                try
                {
                    $id = ArrayUtils::get( $record, static::$pk );
                    $response[$key] = static::updateInternal( $id, $record, $params );
                }
                catch ( \Exception $ex )
                {
                    if ( $singleRow )
                    {
                        throw $ex;
                    }

                    if ( $rollback && $transaction )
                    {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if ( !$continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }

        if ( !empty( $errors ) )
        {
            $msg = array( 'errors' => $errors, 'record' => $response );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $msg );
        }

        //	Commit
        if ( $transaction )
        {
            try
            {
                DB::commit();
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return $singleRow ? current( $response ) : array( 'record' => $response );
    }

    public static function deleteById( $id, $params = [ ] )
    {
        $records = array( array() );

        foreach ( $records as $key => $record )
        {
            ArrayUtils::set( $records[$key], static::$pk, $id );
        }

        return static::bulkDelete( $records, $params );
    }

    public static function deleteByIds( $ids, $params = [ ] )
    {
        if ( !is_array( $ids ) )
        {
            $ids = explode( ",", $ids );
        }

        $records = [ ];

        foreach ( $ids as $id )
        {
            $record = [ ];
            ArrayUtils::set( $record, static::$pk, $id );
            $records[] = $record;
        }

        return static::bulkDelete( $records, $params );
    }

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkDelete( $records, $params = [ ] )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There is no record in the request.' );
        }

        $response = array();
        $transaction = null;
        $errors = array();
        $singleRow = ( 1 === count( $records ) ) ? true : false;
        $rollback = ArrayUtils::getBool( $params, 'rollback' );
        $continue = ArrayUtils::getBool( $params, 'continue' );

        try
        {
            //	Start a transaction
            if ( !$singleRow && $rollback )
            {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ( $records as $key => $record )
            {
                try
                {
                    $id = ArrayUtils::get( $record, static::$pk );
                    $response[$key] = static::deleteInternal( $id, $record, $params );
                }
                catch ( \Exception $ex )
                {
                    if ( $singleRow )
                    {
                        throw $ex;
                    }

                    if ( $rollback && $transaction )
                    {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if ( !$continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }

        if ( !empty( $errors ) )
        {
            $msg = array( 'errors' => $errors, 'record' => $response );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $msg );
        }

        //	Commit
        if ( $transaction )
        {
            try
            {
                DB::commit();
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return $singleRow ? current( $response ) : array( 'record' => $response );
    }

    /**
     * @param       $record
     * @param array $params
     *
     * @return array
     */
    protected static function createInternal( $record, $params = [ ] )
    {
        try
        {
            $model = static::create( $record );
            static::createExtras($model, $record );
        }
        catch ( \PDOException $e )
        {
            throw $e;
        }

        return static::buildResult( $model, $params );
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    public static function updateInternal( $id, $record, $params = [ ] )
    {
        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record to create . ' );
        }

        if ( empty( $id ) )
        {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
        }

        $model = static::find( $id );

        if ( !$model instanceof Model )
        {
            throw new ModelNotFoundException( 'No model found for ' . $id );
        }

        $pk = $model->primaryKey;
        //	Remove the PK from the record since this is an update
        ArrayUtils::remove( $record, $pk );

        try
        {
            $model->update( $record );
            static::updateExtras($model, $record );

            return static::buildResult( $model, $params );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( 'Failed to update resource: ' . $ex->getMessage() );
        }
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    public static function deleteInternal( $id, $record, $params = [ ] )
    {
        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record to create . ' );
        }

        if ( empty( $id ) )
        {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
        }

        $model = static::find( $id );

        if ( !$model instanceof Model )
        {
            throw new ModelNotFoundException( 'No model found for ' . $id );
        }

        try
        {
            static::deleteExtras($model, $record );
            $model->delete();
            $result = static::buildResult( $model, $params );

            return $result;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( 'Failed to delete resource: ' . $ex->getMessage() );
        }
    }

    /**
     * @param       $model
     * @param array $params
     *
     * @return array
     */
    public static function buildResult( $model, $params = [ ] )
    {
        $pk = $model->primaryKey;
        $fields = ArrayUtils::get( $params, 'fields', $pk );
        $related = ArrayUtils::get( $params, 'related' );

        $fieldsArray = explode( ",", $fields );

        $result = array();
        foreach ( $fieldsArray as $f )
        {
            $result[$f] = $model->{$f};
        }

        if ( !empty( $related ) )
        {
            //Todo: implement this.
        }

        $extras = static::buildExtras($model, $params );
        $result = array_merge($result, $extras);


        return $result;
    }

    protected static function createExtras( Model $model, $record )
    {

    }

    protected static function updateExtras( Model $model, $record )
    {

    }

    protected static function deleteExtras( Model $model, $record = [] )
    {

    }

    protected static function buildExtras( Model $model, $params = [] )
    {
        return [];
    }
}