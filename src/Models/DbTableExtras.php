<?php
namespace DreamFactory\Core\Models;

use Illuminate\Database\Query\Builder;

/**
 * DbTableExtras
 *
 * @property integer $id
 * @property integer $service_id
 * @property string  $table
 * @property string  $alias
 * @property string  $label
 * @property string  $plural
 * @property string  $name_field
 * @property string  $description
 * @property string  $model
 * @method static Builder|DbTableExtras whereId($value)
 * @method static Builder|DbTableExtras whereServiceId($value)
 * @method static Builder|DbTableExtras whereTable($value)
 */
class DbTableExtras extends BaseSystemModel
{
    protected $table = 'db_table_extras';

    protected $fillable = ['service_id','table','alias','label','plural','name_field','description','model'];

    protected $casts = ['id' => 'integer', 'service_id' => 'integer'];
}