<?php
namespace DreamFactory\Core\Models;

/**
 * DbRelatedExtras
 *
 * @property integer $id
 * @property integer $service_id
 * @property string  $table
 * @property string  $relationship
 * @property string  $alias
 * @property string  $label
 * @property string  $description
 * @property boolean $collapse
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereId($value)
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereTable($value)
 * @method static \Illuminate\Database\Query\Builder|DbFieldExtras whereRelationship($value)
 */
class DbRelatedExtras extends BaseSystemModel
{
    protected $table = 'db_relationship_extras';

    protected $fillable = [
        'service_id',
        'table',
        'relationship',
        'alias',
        'label',
        'description',
        'collapse',
    ];

    protected $casts = [
        'collapse'   => 'boolean',
        'id'         => 'integer',
        'service_id' => 'integer'
    ];
}