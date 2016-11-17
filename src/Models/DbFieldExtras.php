<?php
namespace DreamFactory\Core\Models;

use Illuminate\Database\Query\Builder;

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
 * @method static Builder|DbFieldExtras whereId($value)
 * @method static Builder|DbFieldExtras whereServiceId($value)
 * @method static Builder|DbFieldExtras whereTable($value)
 * @method static Builder|DbFieldExtras whereField($value)
 * @method static Builder|DbFieldExtras whereAlias($value)
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
        'id'                  => 'integer',
        'service_id'          => 'integer',
        'picklist'            => 'array',
        'validation'          => 'array',
        'db_function'         => 'array',
    ];
}