<?php

namespace DreamFactory\Core\Components;

trait RecordIdCreator
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
}