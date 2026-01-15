<?php

namespace DreamFactory\Core\Models;

use Illuminate\Database\Eloquent\Collection;

trait SingleRecordModel
{
    public static function instance()
    {
        $models = static::all();
        $model = $models->first();

        return $model;
    }

    public static function create(array $attributes = [])
    {
        /** @var Collection $models */
        $models = static::all();
        $model = $models->first();

        if (!empty($model)) {
            $model->update($attributes);

            return $model;
        }

        return parent::create($attributes);
    }
}