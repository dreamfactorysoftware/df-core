<?php
namespace DreamFactory\Core\Models;

/**
 * ScriptType
 *
 * @property string   $name
 * @property string   $class_name
 * @property string   $label
 * @property string   $description
 * @property boolean  $sandboxed
 * @method static \Illuminate\Database\Query\Builder|ScriptType whereName($value)
 * @method static \Illuminate\Database\Query\Builder|ScriptType whereLabel($value)
 */
class ScriptType extends BaseModel
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

    protected $table = 'script_type';

    protected $primaryKey = 'name';

    protected $fillable = ['name', 'class_name', 'label', 'description', 'sandboxed'];

    protected $casts = ['sandboxed' => 'boolean'];

    public $incrementing = false;
}