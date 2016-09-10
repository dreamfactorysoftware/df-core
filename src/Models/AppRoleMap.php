<?php
namespace DreamFactory\Core\Models;

/**
 * Class AppRoleMap
 *
 * @package DreamFactory\Core\Models
 * @method static \Illuminate\Database\Query\Builder|AppRoleMap whereId($value)
 * @method static \Illuminate\Database\Query\Builder|AppRoleMap whereName($value)
 * @method static \Illuminate\Database\Query\Builder|AppRoleMap whereServiceId($value)
 */
class AppRoleMap extends BaseServiceConfigModel
{
    protected $table = 'app_role_map';

    protected $fillable = ['service_id', 'app_id', 'role_id'];

    protected $casts = [
        'id'         => 'integer',
        'service_id' => 'integer',
        'app_id'     => 'integer',
        'role_id'    => 'integer'
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @param int $id
     *
     * @return array
     */
    public static function getConfig($id)
    {
        $maps = static::whereServiceId($id);

        if (!empty($maps)) {
            return $maps->toArray();
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
        static::whereServiceId($id)->delete();
        if (!empty($config)) {
            foreach ($config as $param) {
                //Making sure service_id is the first item in the config.
                //This way service_id will be set first and is available
                //for use right away. This helps setting an auto-generated
                //field that may depend on parent data. See OAuthConfig->setAttribute.
                $param = array_reverse($param, true);
                $param['service_id'] = $id;
                $param = array_reverse($param, true);
                static::create($param);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema =
            [
                'name'        => 'app_role_map',
                'label'       => 'Role for Apps',
                'description' => 'Select a desired Role for your Apps',
                'type'        => 'array',
                'required'    => false,
                'allow_null'  => true
            ];
        $schema['items'] = parent::getConfigSchema();

        return $schema;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        $roleList = [
            [
                'label' => '',
                'name'  => null
            ]
        ];
        $appList = [
            [
                'label' => '',
                'name'  => null
            ]
        ];

        switch ($schema['name']) {
            case 'app_id':
                $apps = App::whereIsActive(1)->get();
                foreach ($apps as $app) {
                    $appList[] = [
                        'label' => $app->name,
                        'name'  => $app->id
                    ];
                }
                $schema['label'] = 'App';
                $schema['type'] = 'picklist';
                $schema['values'] = $appList;
                $schema['description'] = 'Select an App.';
                break;
            case 'role_id':
                $roles = Role::whereIsActive(1)->get();
                foreach ($roles as $role) {
                    $roleList[] = [
                        'label' => $role->name,
                        'name'  => $role->id
                    ];
                }
                $schema['label'] = 'Role';
                $schema['type'] = 'picklist';
                $schema['values'] = $roleList;
                $schema['description'] = 'Select a Role for your App.';
                break;
        }
    }
}