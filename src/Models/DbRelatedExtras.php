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
 * @property boolean $always_fetch
 * @property boolean $flatten
 * @property boolean $flatten_drop_prefix
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
        'always_fetch',
        'flatten',
        'flatten_drop_prefix',
    ];

    protected $casts = [
        'always_fetch'        => 'boolean',
        'flatten'             => 'boolean',
        'flatten_drop_prefix' => 'boolean',
        'id'                  => 'integer',
        'service_id'          => 'integer'
    ];
}