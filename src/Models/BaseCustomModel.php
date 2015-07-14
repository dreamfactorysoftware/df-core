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
            $decodedValue = json_decode($value, true);
        }

        //Not a JSON string.
        if (!empty($value) && empty($decodedValue)) {
            $decodedValue = $value;
        }

        return $decodedValue;
    }
}