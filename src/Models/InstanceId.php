<?php

namespace DreamFactory\Core\Models;

class InstanceId extends BaseSystemModel
{
    protected $table = 'instance_id';

    protected $fillable = ['instance_id'];

    /**
     *
     * @return string
     */
    public static function getInstanceIdOrGenerate() {
        $model = InstanceId::first();
        if(!$model) {
            return self::generateInstanceId()->instance_id;
        }
        return $model['instance_id'];
    }

    /**
     *
     * @return InstanceId
     */
    public static function generateInstanceId() {
        $model = new InstanceId([
            'instance_id' => uniqid(),
        ]);
        $model->save();

        return $model;
    }

    /**
     * Returns InstanceId info cached, or reads from db if not present.
     * Pass in an id.
     *
     * @return string|null
     */
    public static function getCachedInstanceId()
    {
        $cacheKey = 'instance:id';
        $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () {
            return self::getInstanceIdOrGenerate();
        });

        return is_null($result) ? null : $result;
    }

}
