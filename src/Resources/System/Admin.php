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

namespace DreamFactory\Rave\Resources\System;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Models\BaseSystemModel;

class Admin extends BaseSystemResource
{
    protected $resources = [
        Password::RESOURCE_NAME => [
            'name'       => Password::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Resources\\System\\Password',
            'label'      => 'Password'
        ],
        Session::RESOURCE_NAME  => [
            'name'       => Session::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Resources\\System\\Session',
            'label'      => 'Session'
        ]
    ];

    public function getResources()
    {
        return $this->resources;
    }

    protected function handleResource( array $resources )
    {
        try
        {
            return parent::handleResource( $resources );
        }
        catch ( NotFoundException $e )
        {
            //  Perform any pre-request processing
            $this->preProcess();

            $this->response = $this->processRequest();

            if ( false !== $this->response )
            {
                //  Perform any post-request processing
                $this->postProcess();
            }
            //	Inherent failure?
            if ( false === $this->response )
            {
                $what = ( !empty( $this->resourcePath ) ? " for resource '{$this->resourcePath}'" : ' without a resource' );
                $message = ucfirst( $this->action ) . " requests $what are not currently supported by the '{$this->name}' service.";

                throw new BadRequestException( $message );
            }

            //  Perform any response processing
            return $this->respond();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkCreate( array $records, array $params = [ ] )
    {
        $records = static::fixRecords( $records );

        ArrayUtils::set( $params, 'admin', true );

        return parent::bulkCreate( $records, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveById( $id, array $related = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $criteria = $this->getSelectionCriteria();
        $fields = ArrayUtils::get( $criteria, 'select' );
        $model = $modelClass::whereIsSysAdmin( 1 )->with( $related )->find( $id, $fields );

        $data = ( !empty( $model ) ) ? $model->toArray() : [ ];

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function updateById( $id, array $record, array $params = [ ] )
    {
        if ( false === $this->checkAdminById( $id ) )
        {
            throw new BadRequestException( 'No admin user found for the ID supplied.' );
        }

        $record = static::fixRecords( $record );

        return parent::updateById( $id, $record, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function updateByIds( $ids, array $record, array $params = [ ] )
    {
        if ( false === $this->checkAdminByIds( $ids ) )
        {
            throw new BadRequestException( 'Not all users found by the IDs supplied are admins.' );
        }

        $record = static::fixRecords( $record );

        return parent::updateByIds( $ids, $record, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkUpdate( array $records, array $params = [ ] )
    {
        if ( false === $this->checkAdminByRecords( $records ) )
        {
            throw new BadRequestException( 'Not all users found by the records supplied are admins.' );
        }

        $records = static::fixRecords( $records );

        return parent::bulkUpdate( $records, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteById( $id, array $params = [ ] )
    {
        if ( false === $this->checkAdminById( $id ) )
        {
            throw new BadRequestException( 'No admin user found for the ID supplied.' );
        }

        return parent::deleteById( $id, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteByIds( $ids, array $params = [ ] )
    {
        if ( false === $this->checkAdminByIds( $ids ) )
        {
            throw new BadRequestException( 'Not all users found by the IDs supplied are admins.' );
        }

        return parent::deleteByIds( $ids, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkDelete( array $records, array $params = [ ] )
    {
        if ( false === $this->checkAdminByRecords( $records ) )
        {
            throw new BadRequestException( 'Not all users found by the records supplied are admins.' );
        }

        return parent::bulkDelete( $records, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function getSelectionCriteria()
    {
        $criteria = parent::getSelectionCriteria();

        $condition = ArrayUtils::get( $criteria, 'condition' );

        if ( !empty( $condition ) )
        {
            $condition .= ' AND is_sys_admin = "1" ';
        }
        else
        {
            $condition = ' is_sys_admin = "1" ';
        }

        ArrayUtils::set( $criteria, 'condition', $condition );

        return $criteria;
    }

    /**
     * Checks to see if user is an Admin by it's id.
     *
     * @param integer $id
     *
     * @return bool
     */
    protected function checkAdminById( $id )
    {
        $modelClass = $this->model;
        $user = $modelClass::find( $id );

        if ( !empty( $user ) && true === boolval( $user->is_sys_admin ) )
        {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if all users are admins by their ids.
     *
     * @param array|string $ids
     *
     * @return bool
     */
    protected function checkAdminByIds( $ids )
    {
        if ( !is_array( $ids ) )
        {
            $ids = explode( ',', $ids );
        }

        foreach ( $ids as $id )
        {
            if ( false === $this->checkAdminById( $id ) )
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks to see if all users are admins by their records.
     *
     * @param array $records
     *
     * @return bool
     */
    protected function checkAdminByRecords( array $records )
    {
        $ids = [ ];
        $model = $this->getModel();
        foreach ( $records as $record )
        {
            $ids[] = ArrayUtils::get( $record, $model->getPrimaryKey() );
        }

        return $this->checkAdminByIds( $ids );
    }

    /**
     * Fixes supplied records to always set is_set_admin flag to true.
     * Encrypts passwords if it is supplied.
     *
     * @param array $records
     *
     * @return array
     */
    protected static function fixRecords( array $records )
    {

        if ( ArrayUtils::isArrayNumeric( $records ) )
        {
            foreach ( $records as $key => $record )
            {
                $password = ArrayUtils::get( $record, 'password' );
                if ( !empty( $password ) )
                {
                    $password = bcrypt( $password );
                    ArrayUtils::set( $record, 'password', $password );
                }

                ArrayUtils::set( $record, 'is_sys_admin', 1 );

                $records[$key] = $record;
            }
        }
        else
        {
            $password = ArrayUtils::get( $records, 'password' );
            if ( !empty( $password ) )
            {
                $password = bcrypt( $password );
                ArrayUtils::set( $records, 'password', $password );
            }

            ArrayUtils::set( $records, 'is_sys_admin', 1 );
        }

        return $records;
    }
}