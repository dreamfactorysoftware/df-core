<?php

namespace DreamFactory\Core\Models;

class BaseCustomModel extends BaseSystemModel
{
    /**
     * @param $value
     */
    public function setValueAttribute($value)
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        $this->attributes['value'] = $value;
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function getValueAttribute($value)
    {
        if (!is_array($value)) {
            $value = json_decode($value, true);
        }

        return $value;
    }
}