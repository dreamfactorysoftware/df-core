<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\AppRoleMap;
use DreamFactory\Core\Exceptions\BadRequestException;

/**
 * Trait AppRoleMapper
 *
 * @package DreamFactory\Core\Components
 */
trait AppRoleMapper
{
    /**
     * @param integer $id
     * @param boolean $protect
     *
     * @return mixed
     */
    public static function getConfig($id, $protect = true)
    {
        $config = parent::getConfig($id, $protect);

        /** @var AppRoleMap $appRoleMaps */
        $appRoleMaps = AppRoleMap::whereServiceId($id)->get();
        $config['app_role_map'] = (empty($appRoleMaps)) ? [] : $appRoleMaps->toArray();

        return $config;
    }

    /**
     * @param $id
     * @param $config
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
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