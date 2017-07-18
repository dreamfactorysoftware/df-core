<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\SystemResourceTypeInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Resources\System\BaseSystemResource;
use DreamFactory\Core\Utility\Session;
use SystemResourceManager;

class System extends BaseRestService
{
    /**
     * @var integer|null Default application Id used for UI
     */
    public $defaultAppId;
    /**
     * @var integer|null Email service Id used for user invite
     */
    public $inviteEmailServiceId;
    /**
     * @var integer|null Email template Id used for user invite
     */
    public $inviteEmailTemplateId;
    /**
     * @var integer|null Email service Id used for password reset
     */
    public $passwordEmailServiceId;
    /**
     * @var integer|null Email template Id used for password reset
     */
    public $passwordEmailTemplateId;

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        foreach ($this->config as $key => $value) {
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = camel_case($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            } else {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        $resources = [];
        $types = SystemResourceManager::getResourceTypes();
        /** @type SystemResourceTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        return $resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $list = parent::getAccessList();
        $nameField = static::getResourceIdentifier();
        foreach ($this->getResources() as $resource) {
            $name = array_get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                // some resources carry additional operations like admin
                if (false === strpos($name, '/')) {
                    $list[] = $name . '/';
                    $list[] = $name . '/*';
                } else {
                    $list[] = $name;
                }
            }
        }

        return $list;
    }

    public static function getApiDocInfo($service)
    {
        $base = parent::getApiDocInfo($service);

        $apis = [];
        $models = [];
        $resources = SystemResourceManager::getResourceTypes();
        foreach ($resources as $resourceInfo) {
            $resourceClass = $resourceInfo->getClassName();

            if (!class_exists($resourceClass)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $resourceClass);
            }

            $resourceName = $resourceInfo->getName();
            if (Session::checkForAnyServicePermissions($service->name, $resourceName)) {
                /** @type BaseSystemResource $resourceClass */
                $results = $resourceClass::getApiDocInfo($service->name, $resourceInfo->toArray());
                if (isset($results, $results['paths'])) {
                    $apis = array_merge($apis, $results['paths']);
                }
                if (isset($results, $results['definitions'])) {
                    $models = array_merge($models, $results['definitions']);
                }
            }
        }

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], $models);

        return $base;
    }
}