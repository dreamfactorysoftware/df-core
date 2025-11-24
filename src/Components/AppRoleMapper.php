<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\AppRoleMap;

/**
 * Trait AppRoleMapper
 *
 * @package DreamFactory\Core\Components
 */
trait AppRoleMapper
{
    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $config = parent::getConfig($id, $local_config, $protect);

        $appRoleMaps = AppRoleMap::whereServiceId($id)->get();
        $maps = [];
        /** @var AppRoleMap $map */
        foreach ($appRoleMaps as $map) {
            $map->protectedView = $protect;
            $maps[] = $map->toArray();
        }
        $config['app_role_map'] = $maps;

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        if (isset($config['app_role_map'])) {
            $maps = $config['app_role_map'];
            if (!is_array($maps)) {
                throw new BadRequestException('App to Role map must be an array.');
            }
            AppRoleMap::whereServiceId($id)->delete();
            if (!empty($maps)) {
                foreach ($maps as $map) {
                    AppRoleMap::setConfig($id, $map, $local_config);
                }
            }
        }

        return parent::setConfig($id, $config, $local_config);
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        if (isset($config['app_role_map'])) {
            $maps = $config['app_role_map'];
            if (!is_array($maps)) {
                throw new BadRequestException('App to Role map must be an array.');
            }
            AppRoleMap::whereServiceId($id)->delete();
            if (!empty($maps)) {
                foreach ($maps as $map) {
                    AppRoleMap::storeConfig($id, $map);
                }
            }
        }

        parent::storeConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $schema[] = [
            'name'        => 'app_role_map',
            'label'       => 'Role per App',
            'description' => 'Select a desired Role per App for users logging in via this service.',
            'type'        => 'array',
            'required'    => false,
            'allow_null'  => true,
            'items'       => AppRoleMap::getConfigSchema(),
        ];

        return $schema;
    }
}