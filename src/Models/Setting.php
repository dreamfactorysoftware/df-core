<?php

namespace DreamFactory\Core\Models;

/**
 * Setting
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Setting whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Setting whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Setting whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|Setting whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Setting whereLastModifiedDate($value)
 */
class Setting extends BaseSystemModel
{
    protected $table = 'system_setting';

    protected $casts = ['id' => 'integer'];
}