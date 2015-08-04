<?php

namespace DreamFactory\Core\Models;

/**
 * SystemResource
 *
 * @property string  $name
 * @property string  $class_name
 * @property string  $label
 * @property string  $description
 * @property boolean $singleton
 * @property boolean $read_only
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereName($value)
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereLabel($value)
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereSingleton($value)
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereReadOnly($value)
 */
class SystemResource extends BaseModel
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

    protected $table = 'system_resource';

    protected $primaryKey = 'name';

    protected $fillable = ['name', 'label', 'description', 'singleton', 'class_name', 'model_name', 'read_only'];

    protected $casts = ['singleton' => 'boolean', 'read_only' => 'boolean'];

    public $incrementing = false;
}