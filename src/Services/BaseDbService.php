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

namespace DreamFactory\Rave\Services;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\RestException;

abstract class BaseDbService extends BaseRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    //*************************************************************************
    //	Members
    //*************************************************************************

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $main   Main resource or empty for service
     * @param string $sub    Subtending resources if applicable
     * @param string $action Action to validate permission
     */
    protected function validateResourceAccess( $main, /** @noinspection PhpUnusedParameterInspection */
        $sub, $action )
    {
        $_resource = null;
        if ( !empty( $main ) )
        {
            $_resource = $main;
        }

        $this->checkPermission( $action, $_resource );
    }

    /**
     * {@InheritDoc}
     */
    protected function preProcess()
    {
        //	Do validation here
        $this->validateResourceAccess( $this->resource, $this->resourceId, $this->getRequestedAction() );

        parent::preProcess();
    }

    /**
     * {@InheritDoc}
     */
    public function listResources( $include_properties = null )
    {
//        if (version_compare('2.0', $this->request->getApiVersion(), '<'))
//        {
//            $_refresh = ArrayUtils::getBool( $options, 'refresh' );
//            $_verbose = ArrayUtils::getBool( $options, 'include_properties' );
//            $_asComponents = ArrayUtils::getBool( $options, 'as_access_components' );
//            $_resources = array();
//
//            if ( $_asComponents )
//            {
//                $_resources = array( '', '*' );
//            }
//            try
//            {
//                $_result = static::listTables( $_refresh );
//                foreach ( $_result as $_table )
//                {
//                    if ( null != $_name = ArrayUtils::get( $_table, 'name' ) )
//                    {
//                        $_access = $this->getPermissions( $_name );
//                        if ( !empty( $_access ) )
//                        {
//                            if ( $_asComponents || $_verbose )
//                            {
//                                $_resources[] = $_name;
//                            }
//                            else
//                            {
//                                $_table['access'] = $_access;
//                                $_resources[] = $_table;
//                            }
//                        }
//                    }
//                }
//
//                return $_resources;
//            }
//            catch ( RestException $_ex )
//            {
//                throw $_ex;
//            }
//            catch ( \Exception $_ex )
//            {
//                throw new InternalServerErrorException( "Failed to list resources for this service.\n{$_ex->getMessage()}" );
//            }
//        }

        return parent::listResources( $include_properties );
    }
}