<?php

namespace DreamFactory\Core\Models;


/**
 * Class BaseSystemModel
 *
 * @package DreamFactory\Core\Models
 */
class BaseSystemModel extends BaseModel
{
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'last_modified_date';
}