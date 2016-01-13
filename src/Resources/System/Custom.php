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
     * Retrieves records by id.
     *
     * @param integer $id
     * @param array   $related
     *
     * @return array
     */
    protected function retrieveById($id, array $related = [])
    {
        $data = parent::retrieveById($id, $related);

        return (array_key_exists('value', $data)) ? $data['value'] : [];
    }
}