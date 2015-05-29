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

use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Models\App as AppModel;

class App extends BaseSystemResource
{
    /**
     * Handles PATCH action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        if ( !empty( $this->resource ) )
        {
            if ( true === $this->request->getParameterAsBool( 'regenerate' ) )
            {
                /** @var AppModel $appClass */
                $appClass = $this->model;
                $app = $appClass::find( $this->resource )->first();
                $app->api_key = $appClass::generateApiKey( $app->name );
                $app->save();
            }
        }

        return parent::handlePATCH();
    }
}