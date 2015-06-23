<?php
namespace DreamFactory\Core\Models;

/**
 * TokenMap
 *
 * @property integer $id
 * @property integer $user_id
 * @property string  $token
 * @property bool    $forever
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|TokenMap whereId($value)
 * @method static \Illuminate\Database\Query\Builder|TokenMap whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|TokenMap whereToken($value)
 * @method static \Illuminate\Database\Query\Builder|TokenMap whereForever($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereLastModifiedDate($value)
 */
class TokenMap extends BaseSystemModel
{
    protected $table = 'token_map';

    protected $fillable = ['user_id', 'token', 'forever'];

    protected $casts = ['forever' => 'boolean'];
}