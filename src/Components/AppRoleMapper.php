<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\AppRoleMap;
use DreamFactory\Core\Exceptions\BadRequestException;

trait AppRoleMapper
{
    public static function getConfig($id)
    {
        $config = parent::getConfig($id);

        /** @var AppRoleMap $appRoleMaps */
        $appRoleMaps = AppRoleMap::whereServiceId($id)->get();
        $config['app_role_map'] = (empty($appRoleMaps)) ? [] : $appRoleMaps->toArray();

        return $config;
    }

    public static function setConfig($id, $config)
    {
        if (isset($config['app_role_map'])) {
            $maps = $config['app_role_map'];
            if (!is_array($maps)) {
                throw new BadRequestException('App to Role map must be an array.');
            }
            AppRoleMap::setConfig($id, $maps);
        }

        parent::setConfig($id, $config);
    }
}