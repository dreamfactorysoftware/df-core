<?php
namespace DreamFactory\Core\Models;

class NonAdminUser extends User
{
    public static function selectById($id, array $related = [], array $fields = ['*'])
    {
        $fields = static::cleanFields($fields);
        if ($model = static::whereIsSysAdmin(0)->with($related)->find($id, $fields)) {
            return static::cleanResult($model, $fields);
        }

        return null;
    }
}