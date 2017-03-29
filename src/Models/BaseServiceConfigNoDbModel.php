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
    public static function fromStorageFormat($config, $protect = true)
    {
        $model = new static();
        if ($config) {
            $model->setRawAttributes($config, true);
        }
        $model->protectedView = $protect;

        return $model->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public static function toStorageFormat(&$config, $old_config = null)
    {
        $model = new static();
        if ($old_config) {
            $model->setRawAttributes($old_config, true);
        }
        $model->fill($config);
        if ($model->validate($model->attributes)) {
            $config = $model->attributes;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $protect = true)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $create = true)
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