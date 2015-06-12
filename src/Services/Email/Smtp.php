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

namespace DreamFactory\Core\Services\Email;

use Swift_SmtpTransport as SmtpTransport;
use DreamFactory\Library\Utility\ArrayUtils;

class Smtp extends BaseService
{
    protected function setTransport($config)
    {
        $host = ArrayUtils::get($config, 'host');
        $port = ArrayUtils::get($config, 'port');
        $encryption = ArrayUtils::get($config, 'encryption');
        $username = ArrayUtils::get($config, 'username');
        $password = ArrayUtils::get($config, 'password');

        $transport = SmtpTransport::newInstance($host, $port );

        if (!empty($encryption))
        {
            $transport->setEncryption($encryption);
        }

        if (!empty($username) && !empty($password))
        {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        $this->transport = $transport;
    }
}