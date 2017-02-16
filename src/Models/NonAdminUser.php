<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\ApiOptions;

class NonAdminUser extends User
{
    /**
     * {@inheritdoc}
     */
    public static function selectById($id, array $options = [], array $fields = ['*'])
    {
        $fields = static::cleanFields($fields);
        $related = array_get($options, ApiOptions::RELATED, []);
        if (is_string($related)) {
            $related = explode(',', $related);
        }
        if ($model = static::whereIsSysAdmin(0)->with($related)->find($id, $fields)) {
            return static::cleanResult($model, $fields);
        }

        return null;
    }
}