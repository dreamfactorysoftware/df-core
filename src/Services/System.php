<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\SystemResourceTypeInterface;
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
        $types = SystemResourceManager::getResourceTypes();

        $resources = [];
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

    protected function getApiDocSchemas()
    {
        $base = parent::getApiDocSchemas();
        $add = [
            'Metadata' => [
                'type'       => 'object',
                'properties' => [
                    'schema' => [
                        'type'        => 'array',
                        'description' => 'Array of table schema.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                    'count'  => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Record count returned for GET requests.',
                    ],
                ],
            ],
        ];

        return array_merge($base, $add);
    }
}