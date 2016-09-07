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
}