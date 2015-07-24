<?php

namespace DreamFactory\Core\Resources\System;

class Custom extends BaseSystemResource
{
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