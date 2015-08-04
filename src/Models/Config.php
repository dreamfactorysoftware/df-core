<?php

namespace DreamFactory\Core\Models;

use \Cache;

/**
 * Config
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Config whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Config whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Config whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|Config whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Config whereLastModifiedDate($value)
 */
class Config extends BaseSystemModel
{
    use SingleRecordModel;

    protected $primaryKey = 'db_version';

    protected $table = 'system_config';

    protected $fillable = [
        'db_version',
        'login_with_user_name',
        'password_email_service_id',
        'password_email_template_id',
        'api_key',
        'allow_guest_access',
        'guest_role_id',
        'default_app_id'
    ];

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (Config $config){
                if (Cache::has('system_config')) {
                    Cache::forget('system_config');
                }
            }
        );
    }
}