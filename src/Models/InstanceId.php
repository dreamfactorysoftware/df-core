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
}
