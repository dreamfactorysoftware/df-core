<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Components\ServiceManager;
use DreamFactory\Core\Components\ServiceType;
use DreamFactory\Core\Components\SystemResourceManager;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Models\FilePublicPath;
use DreamFactory\Core\Models\LocalEmailConfig;
use DreamFactory\Core\Models\MailGunConfig;
use DreamFactory\Core\Models\MandrillConfig;
use DreamFactory\Core\Models\ScriptConfig;
use DreamFactory\Core\Models\SmtpConfig;
use DreamFactory\Core\Services\Email\Local;
use DreamFactory\Core\Services\Email\MailGun;
use DreamFactory\Core\Services\Email\Mandrill;
use DreamFactory\Core\Services\Email\Smtp;
use DreamFactory\Core\Services\Event;
use DreamFactory\Core\Services\LocalFileService;
use DreamFactory\Core\Services\Script;
use DreamFactory\Core\Services\Swagger;
use DreamFactory\Core\Services\System;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function register()
    {
        \App::register(DfCorsServiceProvider::class);

        // The service manager is used to resolve various services and service types.
        // It also implements the service resolver interface which may be used by other components adding services.
        $this->app->singleton('df.service', function ($app){
            return new ServiceManager($app);
        });

        // The system resource manager is used to resolve various system resource types.
        // It also implements the resource resolver interface which may be used by other components adding resources.
        $this->app->singleton('df.system.resource', function ($app){
            return new SystemResourceManager($app);
        });

        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'        => 'system',
                    'label'       => 'System Management Service',
                    'description' => 'Service supporting management of the system.',
                    'group'       => ServiceTypeGroups::SYSTEM,
                    'singleton'   => true,
                    'factory'       => function ($config){
                        return new System($config);
                    },
                ])
            );
            $df->addType(
                new ServiceType([
                    'name'        => 'swagger',
                    'label'       => 'Swagger API Docs',
                    'description' => 'API documenting and testing service using Swagger specifications.',
                    'group'       => ServiceTypeGroups::API_DOC,
                    'singleton'   => true,
                    'factory'       => function ($config){
                        return new Swagger($config);
                    },
                ])
            );

            $df->addType(
                new ServiceType([
                    'name'        => 'event',
                    'label'       => 'Event Service',
                    'description' => 'Service that allows clients to subscribe to system broadcast events.',
                    'group'       => ServiceTypeGroups::EVENT,
                    'singleton'   => true,
                    'factory'       => function ($config){
                        return new Event($config);
                    },
                ])
            );

            $df->addType(
                new ServiceType([
                    'name'           => 'script',
                    'label'          => 'Custom Scripting Service',
                    'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
                    'group'          => ServiceTypeGroups::CUSTOM,
                    'config_handler' => ScriptConfig::class,
                    'factory'          => function ($config){
                        return new Script($config);
                    },
                ])
            );

            $df->addType(
                new ServiceType([
                    'name'           => 'local_file',
                    'label'          => 'Local File Service',
                    'description'    => 'File service supporting the local file system.',
                    'group'          => ServiceTypeGroups::FILE,
                    'config_handler' => FilePublicPath::class,
                    'factory'          => function ($config){
                        return new LocalFileService($config);
                    },
                ])
            );

            $df->addType(
                new ServiceType([
                    'name'           => 'local_email',
                    'label'          => 'Local Email Service',
                    'description'    => 'Local email service using system configuration.',
                    'group'          => ServiceTypeGroups::EMAIL,
                    'config_handler' => LocalEmailConfig::class,
                    'factory'          => function ($config){
                        return new Local($config);
                    },
                ])
            );
            $df->addType(
                new ServiceType([
                    'name'           => 'smtp_email',
                    'label'          => 'SMTP Email Service',
                    'description'    => 'SMTP-based email service',
                    'group'          => ServiceTypeGroups::EMAIL,
                    'config_handler' => SmtpConfig::class,
                    'factory'          => function ($config){
                        return new Smtp($config);
                    },
                ])
            );
            $df->addType(
                new ServiceType([
                    'name'           => 'mailgun_email',
                    'label'          => 'Mailgun Email Service',
                    'description'    => 'Mailgun email service',
                    'group'          => ServiceTypeGroups::EMAIL,
                    'config_handler' => MailGunConfig::class,
                    'factory'          => function ($config){
                        return new MailGun($config);
                    },
                ])
            );
            $df->addType(
                new ServiceType([
                    'name'           => 'mandrill_email',
                    'label'          => 'Mandrill Email Service',
                    'description'    => 'Mandrill email service',
                    'group'          => ServiceTypeGroups::EMAIL,
                    'config_handler' => MandrillConfig::class,
                    'factory'          => function ($config){
                        return new Mandrill($config);
                    },
                ])
            );
        });

        \Event::subscribe(new ServiceEventHandler());

        // Add our database drivers.
        \App::register(DfSqlDbServiceProvider::class);

        // If user required, add provider here.
        if (class_exists('DreamFactory\Core\User\ServiceProvider')) {
            \App::register('DreamFactory\Core\User\ServiceProvider');
        }

        // If sqldb required, add provider here.
        if (class_exists('DreamFactory\Core\SqlDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\SqlDb\ServiceProvider');
        }

        // If mongodb required, add provider here.
        if (class_exists('DreamFactory\Core\MongoDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\MongoDb\ServiceProvider');
        }

        // If aws required, add provider here.
        if (class_exists('DreamFactory\Core\Aws\ServiceProvider')) {
            \App::register('DreamFactory\Core\Aws\ServiceProvider');
        }

        // If azure required, add provider here.
        if (class_exists('DreamFactory\Core\Azure\ServiceProvider')) {
            \App::register('DreamFactory\Core\Azure\ServiceProvider');
        }

        // If adldap required, add provider here.
        if (class_exists('DreamFactory\Core\ADLdap\ServiceProvider')) {
            \App::register('DreamFactory\Core\ADLdap\ServiceProvider');
        }

        // If couchdb required, add provider here.
        if (class_exists('DreamFactory\Core\CouchDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\CouchDb\ServiceProvider');
        }

        // If couchdb required, add provider here.
        if (class_exists('DreamFactory\Core\Soap\ServiceProvider')) {
            \App::register('DreamFactory\Core\Soap\ServiceProvider');
        }

        // If couchdb required, add provider here.
        if (class_exists('DreamFactory\Core\Rws\ServiceProvider')) {
            \App::register('DreamFactory\Core\Rws\ServiceProvider');
        }

        // If rackspace required, add provider here.
        if (class_exists('DreamFactory\Core\Rackspace\ServiceProvider')) {
            \App::register('DreamFactory\Core\Rackspace\ServiceProvider');
        }

        // If salesforce required, add provider here.
        if (class_exists('DreamFactory\Core\Salesforce\ServiceProvider')) {
            \App::register('DreamFactory\Core\Salesforce\ServiceProvider');
        }
    }
}
