<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;

/**
 * ServiceType
 *
 * @property string  $name
 * @property string  $class_name
 * @property string  $config_handler
 * @property string  $label
 * @property string  $description
 * @property string  $group
 * @property boolean $singleton
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereName($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereLabel($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereSingleton($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereGroup($value)
 */
class ServiceType extends BaseModel
{
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'last_modified_date';

    protected $table = 'service_type';

    protected $primaryKey = 'name';

    protected $guarded = ['*']; //

    protected $hidden = ['class_name', 'config_handler'];

    protected $appends = ['config_schema'];

    protected $casts = ['singleton' => 'boolean'];

    public $incrementing = false;

    public function getConfigSchemaAttribute()
    {
        if (is_subclass_of($this->config_handler, ServiceConfigHandlerInterface::class)) {
            /** @var ServiceConfigHandlerInterface $handler */
            $handler = $this->config_handler;

            return $handler::getConfigSchema();
        }

        return null;
    }
}