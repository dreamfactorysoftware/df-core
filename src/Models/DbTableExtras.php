<?php
namespace DreamFactory\Core\Models;

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
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereId($value)
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereTable($value)
 */
class DbTableExtras extends BaseSystemModel
{
    protected $table = 'db_table_extras';

    protected $fillable = ['service_id','table','alias','label','plural','name_field','description','model'];

    protected $casts = ['id' => 'integer', 'service_id' => 'integer'];
}