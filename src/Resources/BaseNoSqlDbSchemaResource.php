<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Exceptions\NotImplementedException;

// Handle administrative options, table add, delete, etc
abstract class BaseNoSqlDbSchemaResource extends BaseDbSchemaResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * General method for creating a pseudo-random identifier
     *
     * @param string $table Name of the table where the item will be stored
     *
     * @return string
     */
    protected static function createRecordId($table)
    {
        $randomTime = abs(time());

        if ($randomTime == 0) {
            $randomTime = 1;
        }

        $random1 = rand(1, $randomTime);
        $random2 = rand(1, 2000000000);
        $generateId = strtolower(md5($random1 . $table . $randomTime . $random2));
        $randSmall = rand(10, 99);

        return $generateId . $randSmall;
    }

    /**
     * {@inheritdoc}
     */
    public function describeField($table, $field, $refresh = false)
    {
        throw new NotImplementedException('Not currently supported for NoSQL database services.');
    }

    /**
     * {@inheritdoc}
     */
    public function createField($table, $field, $properties = array(), $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException('Not currently supported for NoSQL database services.');
    }

    /**
     * {@inheritdoc}
     */
    public function updateField(
        $table,
        $field,
        $properties = array(),
        $allow_delete_parts = false,
        $return_schema = false
    ){
        throw new NotImplementedException('Not currently supported for NoSQL database services.');
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField($table, $field)
    {
        throw new NotImplementedException('Not currently supported for NoSQL database services.');
    }
}