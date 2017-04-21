<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;

/**
 * Class BaseServiceConfigNoDbModel
 *
 * @package DreamFactory\Core\Models
 */
class BaseServiceConfigNoDbModel extends NoDbModel implements ServiceConfigHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public static function handlesStorage()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $model = new static((array)$local_config);
        $model->protectedView = $protect;

        return $model->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        $model = new static((array)$local_config);
        $model->fill($config);
        if ($model->validate($model->attributes)) {
            $config = $model->attributes;
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        // saving is not necessary here due to this class not handling storage
    }

    /**
     * {@inheritdoc}
     */
    public static function removeConfig($id)
    {
        // deleting is not necessary here due to this class not handling storage
    }

    /**
     * {@inheritdoc}
     */
    public static function getAvailableConfigs()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        // drop the field name keys here
        return array_values(static::getSchema());
    }
}