<?php
namespace DreamFactory\Core\Models;

/**
 * DbFieldExtras
 *
 * @property integer $id
 * @property integer $service_id
 * @property string  $table
 * @property string  $field
 * @property string  $alias
 * @property string  $label
 * @property string  $description
 * @property array   $picklist
 * @property array   $validation
 * @property string  $extra_type
 * @property string  $client_info
 * @property array   $db_function
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereId($value)
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereTable($value)
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereField($value)
 */
class DbFieldExtras extends BaseSystemModel
{
    protected $table = 'db_field_extras';

    protected $fillable = [
        'service_id',
        'table',
        'field',
        'alias',
        'label',
        'picklist',
        'validation',
        'description',
        'extra_type',
        'client_info',
        'db_function',
    ];

    protected $casts = [
        'picklist'    => 'array',
        'validation'  => 'array',
        'db_function' => 'array',
        'id'          => 'integer',
        'service_id'  => 'integer'
    ];
}