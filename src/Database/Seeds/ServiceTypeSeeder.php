<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Core\Database\Seeds;

class ServiceTypeSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\ServiceType';

    protected $records = [
        [
            'name'           => 'system',
            'class_name'     => 'DreamFactory\\Core\\Services\\System',
            'config_handler' => null,
            'label'          => 'System Management Service',
            'description'    => 'Service supporting management of the system.',
            'group'          => 'system',
            'singleton'      => 1
        ],
        [
            'name'           => 'swagger',
            'class_name'     => 'DreamFactory\\Core\\Services\\Swagger',
            'config_handler' => null,
            'label'          => 'Swagger API Docs',
            'description'    => 'API documenting and testing service using Swagger specifications.',
            'group'          => 'api_doc',
            'singleton'      => 1
        ],
        [
            'name'           => 'event',
            'class_name'     => 'DreamFactory\\Core\\Services\\Event',
            'config_handler' => null,
            'label'          => 'Event Service',
            'description'    => 'Service that allows clients to subscribe to system broadcast events.',
            'group'          => 'event',
            'singleton'      => 1
        ],
        [
            'name'           => 'script',
            'class_name'     => 'DreamFactory\\Core\\Services\\Script',
            'config_handler' => 'DreamFactory\\Core\\Models\\ScriptConfig',
            'label'          => 'Custom Scripting Service',
            'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
            'group'          => 'script',
            'singleton'      => 0
        ],
        [
            'name'           => 'local_file',
            'class_name'     => 'DreamFactory\\Core\\Services\\LocalFileService',
            'config_handler' => null,
            'label'          => 'Local File Service',
            'description'    => 'File service supporting the local file system.',
            'group'          => 'file',
            'singleton'      => 1
        ],
        [
            'name'           => 'local_email',
            'class_name'     => 'DreamFactory\\Core\\Services\\Email\\Local',
            'config_handler' => 'DreamFactory\\Core\\Models\\EmailServiceConfig',
            'label'          => 'Local Email Service',
            'description'    => 'Local email service using system configuration.',
            'group'          => 'email',
            'singleton'      => 1
        ],
        [
            'name'           => 'smtp_email',
            'class_name'     => 'DreamFactory\\Core\\Services\\Email\\Smtp',
            'config_handler' => 'DreamFactory\\Core\\Models\\EmailServiceConfig',
            'label'          => 'SMTP Email Service',
            'description'    => 'SMTP-based email service',
            'group'          => 'email',
            'singleton'      => 0
        ],
        [
            'name'           => 'mailgun_email',
            'class_name'     => 'DreamFactory\\Core\\Services\\Email\\Mailgun',
            'config_handler' => 'DreamFactory\\Core\\Models\\EmailServiceConfig',
            'label'          => 'Mailgun Email Service',
            'description'    => 'Mailgun email service',
            'group'          => 'email',
            'singleton'      => 0
        ],
        [
            'name'           => 'mandrill_email',
            'class_name'     => 'DreamFactory\\Core\\Services\\Email\\Mandrill',
            'config_handler' => 'DreamFactory\\Core\\Models\\EmailServiceConfig',
            'label'          => 'Mandrill Email Service',
            'description'    => 'Mandrill email service',
            'group'          => 'email',
            'singleton'      => 0
        ]
    ];
}
