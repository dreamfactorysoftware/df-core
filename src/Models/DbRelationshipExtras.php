<?php
namespace DreamFactory\Core\Models;

use Illuminate\Database\Query\Builder;

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
 * @method static Builder|DbRelationshipExtras whereId($value)
 * @method static Builder|DbRelationshipExtras whereServiceId($value)
 * @method static Builder|DbRelationshipExtras whereTable($value)
 * @method static Builder|DbRelationshipExtras whereRelationship($value)
 * @method static Builder|DbRelationshipExtras whereAlias($value)
 */
class DbRelationshipExtras extends BaseSystemModel
{
    protected $table = 'db_relationship_extras';

    protected $guarded = ['id'];

    protected $casts = [
        'id'                  => 'integer',
        'service_id'          => 'integer',
        'always_fetch'        => 'boolean',
        'flatten'             => 'boolean',
        'flatten_drop_prefix' => 'boolean',
    ];
}