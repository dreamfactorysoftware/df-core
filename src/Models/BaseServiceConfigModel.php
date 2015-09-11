<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;
use DreamFactory\Core\Database\ColumnSchema;

/**
 * Class BaseServiceConfigModel
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
     * @var array
     */
    protected $fillable = ['service_id'];

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
     * @param int $id
     *
     * @return array
     */
    public static function getConfig($id)
    {
        $model = static::find($id);

        if (!empty($model)) {
            return $model->toArray();
        } else {
            return [];
        }
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
    public static function setConfig($id, $config)
    {
        $model = static::find($id);
        if (!empty($model)) {
            $model->update($config);
        } else {
            //Making sure service_id is the first item in the config.
            //This way service_id will be set first and is available
            //for use right away. This helps setting an auto-generated
            //field that may depend on parent data. See OAuthConfig->setAttribute.
            $config = array_reverse($config, true);
            $config['service_id'] = $id;
            $config = array_reverse($config, true);
            static::create($config);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function removeConfig($id)
    {
        // deleting is not necessary here due to cascading on_delete relationship in database
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
        unset($schema['php_type'], $schema['pdo_type'], $schema['db_type'], $schema['auto_increment'], $schema['is_index']);
    }

    /**
     * @param array     $config
     * @param array     $rules
     * @param bool|true $create
     *
     * @return \Illuminate\Validation\Validator
     */
    protected static function makeValidator(array $config, array $rules, $create = true)
    {
        if ($create && !empty($rules)) {
            $validator = \Validator::make($config, $rules);
        } else {
            $newRules = [];
            foreach ($config as $key => $value) {
                if(array_key_exists($key, $rules)){
                    $newRules[$key] = $rules[$key];
                }
            }

            $validator = \Validator::make($config, $newRules);
        }

        return $validator;
    }
}