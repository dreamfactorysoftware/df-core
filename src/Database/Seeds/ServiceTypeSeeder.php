<?php
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
