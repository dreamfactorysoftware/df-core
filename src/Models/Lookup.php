<?php

namespace DreamFactory\Core\Models;

/**
 * Lookup
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Lookup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereIsPrivate($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereLastModifiedDate($value)
 */
class Lookup extends BaseSystemLookup
{
    protected $table = 'system_lookup';
}