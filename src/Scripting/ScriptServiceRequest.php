<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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
namespace DreamFactory\Core\Scripting;

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;

/**
 * Class ScriptServiceRequest
 *
 */
class ScriptServiceRequest implements ServiceRequestInterface
{
    use InternalServiceRequest;

    public function __construct( $method = Verbs::GET, $parameters = [ ], $headers = [ ] )
    {
        $this->setMethod( $method );
        $this->setParameters( $parameters );
        $this->setHeaders( $headers );
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestorType()
    {
        return ServiceRequestorTypes::SCRIPT;
    }
}