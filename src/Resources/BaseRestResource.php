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

namespace DreamFactory\Rave\Resources;

use DreamFactory\Rave\Components\RestHandler;

/**
 * Class BaseRestResource
 *
 * @package DreamFactory\Rave\Resources
 */
class BaseRestResource extends RestHandler
{
    public function getApiDocInfo()
    {
        /**
         * Some basic apis and models used in DSP REST interfaces
         */
        return [
            'apis'         => [
                [
                    'path'        => '/{api_name}/' . $this->name,
                    'operations'  => [],
                    'description' => 'No operations currently defined for this resource.',
                ],
            ],
        ];
    }
}