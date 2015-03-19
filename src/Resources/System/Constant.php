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

use DreamFactory\Rave\Models\ServiceType;
use DreamFactory\Rave\Resources\BaseRestSystemResource;
use \Config;

class Constant extends BaseRestSystemResource
{

    public function __construct( $settings = array() )
    {
        parent::__construct( $settings );
    }

    protected function handleGET()
    {
        $resources = array();
        if ( empty( $this->_resourceId ) )
        {
            $resources = array(
                array( 'name' => 'service_type', 'label' => 'Service Type' ),
            );
        }
        else
        {
            switch ( $this->_resourceId )
            {
                case 'service_type':
                    $services = ServiceType::all()->toArray();
                    $resources = $this->makeResourceList( $services, null, false );
                    break;
                default;
                    break;
            }
        }

        return array( 'resource' => $resources );
    }

    protected function handlePOST()
    {
        return false;
    }
}