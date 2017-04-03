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
     * @var bool
     */
    public static $alwaysNewOnSet = true;

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'app_id':
                $apps = App::whereIsActive(1)->get();
                $appList = [];
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
                $roleList = [];
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
