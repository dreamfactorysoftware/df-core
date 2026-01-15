<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use Illuminate\Database\Query\Builder;

/**
 * Class BaseServiceConfigModel
 *
 * @property integer $service_id
 * @method static Builder|BaseServiceConfigModel whereServiceId($value)
 *
 * @package DreamFactory\Core\Models
 */
abstract class BaseServiceConfigModel extends BaseModel implements ServiceConfigHandlerInterface
{
    /**
     * @var string
     */
    protected $primaryKey = 'service_id';

    /**
     * @var array Attributes tend to be dynamic, so let them all be assignable
     */
    protected $guarded = [];

    protected $casts = ['service_id' => 'integer'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public static $alwaysNewOnSet = false;

    public static function getServiceIdField()
    {
        return 'service_id';
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesStorage()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $out = [];
        /** @var BaseServiceConfigModel $model */
        /** @noinspection PhpUndefinedMethodInspection */
        if ($model = static::whereServiceId($id)->first()) {
            $model->protectedView = $protect;

            $out = $model->toArray();
        }
        if ($local_config) {
            $out = array_merge((array)$out, $local_config);
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        if ($id) {
            //Making sure service_id is the first item in the config.
            //This way service_id will be set first and is available
            //for use right away. This helps setting an auto-generated
            //field that may depend on parent data. See OAuthConfig->setAttribute.
            $config = array_reverse($config, true);
            $config[static::getServiceIdField()] = $id;
            $config = array_reverse($config, true);
            /** @noinspection PhpUndefinedMethodInspection */
            $model = (static::$alwaysNewOnSet ? new static() : static::firstOrNew([static::getServiceIdField() => $id]));
        } else {
            $model = new static();
        }
        /** @var BaseServiceConfigModel $model */
        $config = array_only($config, $model->getFillable());
        $model->fill((array)$config);
        $model->validate($model->attributes);
        if ($id) { // only save if the service has been created
            $model->save();
        }

        return $local_config; // by default, just return what the service passed us
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        //Making sure service_id is the first item in the config.
        //This way service_id will be set first and is available
        //for use right away. This helps setting an auto-generated
        //field that may depend on parent data. See OAuthConfig->setAttribute.
        $config = array_reverse($config, true);
        $config[static::getServiceIdField()] = $id;
        $config = array_reverse($config, true);

        /** @noinspection PhpUndefinedMethodInspection */
        /** @var BaseServiceConfigModel $model */
        $model = (static::$alwaysNewOnSet ? new static() : static::firstOrNew([static::getServiceIdField() => $id]));
        $config = array_only($config, $model->getFillable());
        $model->fill((array)$config);
        $model->save();
    }

    /**
     * {@inheritdoc}
     */
    public static function removeConfig($id)
    {
        static::destroy($id);
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
        $model = new static;

        $schema = $model->getTableSchema();
        if ($schema) {
            $out = [];
            foreach ($schema->columns as $name => $column) {
                // Skip if column is hidden
                if (in_array($name, $model->getHidden())) {
                    continue;
                }
                /** @var ColumnSchema $column */
                if (('service_id' === $name) || $column->autoIncrement) {
                    continue;
                }

                $temp = $column->toArray();
                static::prepareConfigSchemaField($temp);
                $out[] = $temp;
            }

            return $out;
        }

        return null;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        // clear out server-specific info
        unset($schema['db_type'], $schema['auto_increment'], $schema['is_index']);
    }
}