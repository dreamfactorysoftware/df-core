<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\MailGunConfig;
use DreamFactory\Core\Models\MandrillConfig;
use DreamFactory\Core\Models\SmtpConfig;
use DreamFactory\Core\Models\FilePublicPath;
use DreamFactory\Core\Models\ScriptConfig;
use DreamFactory\Core\Models\ServiceType;
use DreamFactory\Core\Services\Email\Local;
use DreamFactory\Core\Services\Email\MailGun;
use DreamFactory\Core\Services\Email\Mandrill;
use DreamFactory\Core\Services\Email\Smtp;
use DreamFactory\Core\Services\Event;
use DreamFactory\Core\Services\LocalFileService;
use DreamFactory\Core\Services\Script;
use DreamFactory\Core\Services\Swagger;
use DreamFactory\Core\Services\System;

class ServiceTypeSeeder extends BaseModelSeeder
{
    protected $modelClass = ServiceType::class;

    protected $records = [
        [
            'name'           => 'system',
            'class_name'     => System::class,
            'config_handler' => null,
            'label'          => 'System Management Service',
            'description'    => 'Service supporting management of the system.',
            'group'          => ServiceTypeGroups::SYSTEM,
            'singleton'      => true
        ],
        [
            'name'           => 'swagger',
            'class_name'     => Swagger::class,
            'config_handler' => null,
            'label'          => 'Swagger API Docs',
            'description'    => 'API documenting and testing service using Swagger specifications.',
            'group'          => ServiceTypeGroups::API_DOC,
            'singleton'      => true
        ],
        [
            'name'           => 'event',
            'class_name'     => Event::class,
            'config_handler' => null,
            'label'          => 'Event Service',
            'description'    => 'Service that allows clients to subscribe to system broadcast events.',
            'group'          => ServiceTypeGroups::EVENT,
            'singleton'      => true
        ],
        [
            'name'           => 'script',
            'class_name'     => Script::class,
            'config_handler' => ScriptConfig::class,
            'label'          => 'Custom Scripting Service',
            'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
            'group'          => ServiceTypeGroups::CUSTOM,
            'singleton'      => false
        ],
        [
            'name'           => 'local_file',
            'class_name'     => LocalFileService::class,
            'config_handler' => FilePublicPath::class,
            'label'          => 'Local File Service',
            'description'    => 'File service supporting the local file system.',
            'group'          => ServiceTypeGroups::FILE,
            'singleton'      => false
        ],
        [
            'name'           => 'local_email',
            'class_name'     => Local::class,
            'config_handler' => null,
            'label'          => 'Local Email Service',
            'description'    => 'Local email service using system configuration.',
            'group'          => ServiceTypeGroups::EMAIL,
            'singleton'      => false
        ],
        [
            'name'           => 'smtp_email',
            'class_name'     => Smtp::class,
            'config_handler' => SmtpConfig::class,
            'label'          => 'SMTP Email Service',
            'description'    => 'SMTP-based email service',
            'group'          => ServiceTypeGroups::EMAIL,
            'singleton'      => false
        ],
        [
            'name'           => 'mailgun_email',
            'class_name'     => MailGun::class,
            'config_handler' => MailGunConfig::class,
            'label'          => 'Mailgun Email Service',
            'description'    => 'Mailgun email service',
            'group'          => ServiceTypeGroups::EMAIL,
            'singleton'      => false
        ],
        [
            'name'           => 'mandrill_email',
            'class_name'     => Mandrill::class,
            'config_handler' => MandrillConfig::class,
            'label'          => 'Mandrill Email Service',
            'description'    => 'Mandrill email service',
            'group'          => ServiceTypeGroups::EMAIL,
            'singleton'      => false
        ]
    ];
}
