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

    /**
     * {@inheritdoc}
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleResource( array $resources )
    {
        try
        {
            return parent::handleResource( $resources );
        }
        catch ( NotFoundException $e )
        {
            if ( is_numeric( $this->resource ) )
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
            else
            {
                throw $e;
            }
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
        $record = static::fixRecords( $record );

        ArrayUtils::set( $params, 'admin', true );

        return parent::updateById( $id, $record, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function updateByIds( $ids, array $record, array $params = [ ] )
    {
        $record = static::fixRecords( $record );

        ArrayUtils::set( $params, 'admin', true );

        return parent::updateByIds( $ids, $record, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkUpdate( array $records, array $params = [ ] )
    {
        $records = static::fixRecords( $records );

        ArrayUtils::set( $params, 'admin', true );

        return parent::bulkUpdate( $records, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteById( $id, array $params = [ ] )
    {
        ArrayUtils::set( $params, 'admin', true );

        return parent::deleteById( $id, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteByIds( $ids, array $params = [ ] )
    {
        ArrayUtils::set( $params, 'admin', true );

        return parent::deleteByIds( $ids, $params );
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkDelete( array $records, array $params = [ ] )
    {
        ArrayUtils::set( $params, 'admin', true );

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
                ArrayUtils::set( $record, 'is_sys_admin', 1 );
                $records[$key] = $record;
            }
        }
        else
        {
            ArrayUtils::set( $records, 'is_sys_admin', 1 );
        }

        return $records;
    }
}