<?php
namespace DreamFactory\Core\Models;

/**
 * DbTableExtras
 *
 * @property integer    $id
 * @property integer    $service_id
 * @property string     $table
 * @property string     $label
 * @property string     $plural
 * @property string     $name_field
 * @property string     $description
 * @property string     $model
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereId($value)
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereServiceId($value)
 */
class DbTableExtras extends BaseSystemModel
{
    protected $table = 'db_table_extras';
}