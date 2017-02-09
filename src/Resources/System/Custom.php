<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Models\SystemCustom;

class Custom extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = SystemCustom::class;

    /**
     * @inheritdoc
     */
    protected function handleGET()
    {
        $data = parent::handleGET();

        return (array_key_exists('value', $data)) ? $data['value'] : $data;
    }
}