<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Services\Email\Local;
use DreamFactory\Core\Services\Email\MailGun;
use DreamFactory\Core\Services\Email\Mandrill;
use DreamFactory\Core\Services\Email\Smtp;
use DreamFactory\Core\Services\Event;
use DreamFactory\Core\Services\LocalFileService;
use DreamFactory\Core\Services\Script;
use DreamFactory\Core\Services\Swagger;
use DreamFactory\Core\Services\System;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ServiceFactory
{
    /**
     * The IoC container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * Create a new service factory instance.
     *
     * @param  \Illuminate\Contracts\Container\Container $container
     *
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Establish a service based on the configuration.
     *
     * @param  array  $config
     * @param  string $name
     *
     * @return ServiceInterface
     */
    public function make(array $config, $name = null)
    {
        $config = $this->parseConfig($config, $name);

        return $this->createService($config);
    }

    /**
     * Create a service instance.
     *
     * @param  array $config
     *
     * @return ServiceInterface
     */
    protected function createService(array $config)
    {
        $type = $config['type'];
        if ($this->container->bound($key = "df.service.{$type}")) {
            return $this->container->make($key, [$config]);
        }

        switch ($type) {
            case 'system':
//                    'label'          => 'System Management Service',
//                    'description'    => 'Service supporting management of the system.',
//                    'group'          => ServiceTypeGroups::SYSTEM,
//                    'singleton'      => true
                return new System($config);

            case 'swagger':
//                    'label'          => 'Swagger API Docs',
//                    'description'    => 'API documenting and testing service using Swagger specifications.',
//                    'group'          => ServiceTypeGroups::API_DOC,
//                    'singleton'      => true
                return new Swagger($config);

            case 'event':
//                    'label'          => 'Event Service',
//                    'description'    => 'Service that allows clients to subscribe to system broadcast events.',
//                    'group'          => ServiceTypeGroups::EVENT,
//                    'singleton'      => true
                return new Event($config);

            case 'script':
//                    'config_handler' => ScriptConfig::class,
//                    'label'          => 'Custom Scripting Service',
//                    'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
//                    'group'          => ServiceTypeGroups::CUSTOM,
                return new Script($config);

            case 'local_file':
//            'config_handler' => FilePublicPath::class,
//            'label'          => 'Local File Service',
//            'description'    => 'File service supporting the local file system.',
//            'group'          => ServiceTypeGroups::FILE,
                return new LocalFileService($config);

            case 'local_email':
//            'config_handler' => LocalEmailConfig::class,
//            'label'          => 'Local Email Service',
//            'description'    => 'Local email service using system configuration.',
//            'group'          => ServiceTypeGroups::EMAIL,
                return new Local($config);

            case 'smtp_email':
//            'config_handler' => SmtpConfig::class,
//            'label'          => 'SMTP Email Service',
//            'description'    => 'SMTP-based email service',
//            'group'          => ServiceTypeGroups::EMAIL,
                return new Smtp($config);

            case 'mailgun_email':
//            'config_handler' => MailGunConfig::class,
//            'label'          => 'Mailgun Email Service',
//            'description'    => 'Mailgun email service',
//            'group'          => ServiceTypeGroups::EMAIL,
                return new MailGun($config);

            case 'mandrill_email':
//            'config_handler' => MandrillConfig::class,
//            'label'          => 'Mandrill Email Service',
//            'description'    => 'Mandrill email service',
//            'group'          => ServiceTypeGroups::EMAIL,
                return new Mandrill($config);
        }

        throw new InvalidArgumentException("Unsupported service type [$type]");
    }

    /**
     * Parse and prepare the service configuration.
     *
     * @param  array  $config
     * @param  string $name
     *
     * @return array
     */
    protected function parseConfig(array $config, $name)
    {
        return array_add(array_add($config, 'prefix', ''), 'name', $name);
    }
}
