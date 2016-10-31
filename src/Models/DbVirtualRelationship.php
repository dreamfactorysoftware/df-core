<?php
namespace DreamFactory\Core\Models;

use Illuminate\Database\Query\Builder;

/**
 * DbVirtualRelationship
 *
 * @property integer $id
 * @property string  $type
 * @property integer $service_id
 * @property string  $table
 * @property string  $field
 * @property integer $ref_service_id
 * @property string  $ref_table
 * @property string  $ref_field
 * @property string  $ref_on_update
 * @property string  $ref_on_delete
 * @property integer $junction_service_id
 * @property string  $junction_table
 * @property string  $junction_field
 * @property string  $junction_ref_field
 * @method static Builder|DbVirtualRelationship whereId($value)
 * @method static Builder|DbVirtualRelationship whereType($value)
 * @method static Builder|DbVirtualRelationship whereServiceId($value)
 * @method static Builder|DbVirtualRelationship whereTable($value)
 * @method static Builder|DbVirtualRelationship whereField($value)
 * @method static Builder|DbVirtualRelationship whereRefServiceId($value)
 * @method static Builder|DbVirtualRelationship whereRefTable($value)
 * @method static Builder|DbVirtualRelationship whereRefField($value)
 * @method static Builder|DbVirtualRelationship whereJunctionServiceId($value)
 * @method static Builder|DbVirtualRelationship whereJunctionTable($value)
 * @method static Builder|DbVirtualRelationship whereJunctionField($value)
 * @method static Builder|DbVirtualRelationship whereJunctionRefField($value)
 */
class DbVirtualRelationship extends BaseSystemModel
{
    protected $table = 'db_virtual_relationship';

    protected $guarded = ['id'];

    protected $casts = [
        'id'                  => 'integer',
        'service_id'          => 'integer',
        'ref_service_id'      => 'integer',
        'junction_service_id' => 'integer',
    ];
}