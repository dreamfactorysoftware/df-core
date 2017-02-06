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

    /**
     * {@inheritdoc}
     */
    public static function selectById($id, array $related = [], array $fields = ['*'])
    {
        $fields = static::cleanFields($fields);
        $response = parent::selectById($id, $related, $fields);

        return static::cleanResult($response, $fields);
    }

    /**
     * {@inheritdoc}
     */
    public static function selectByIds($ids, array $related = [], array $criteria = [])
    {
        $criteria = static::cleanCriteria($criteria);
        $response = parent::selectByIds($ids, $related, $criteria);

        return static::cleanResult($response, array_get($criteria, 'select'));
    }

    /**
     * {@inheritdoc}
     */
    public static function selectByRequest(array $criteria = [], array $related = [])
    {
        $criteria = static::cleanCriteria($criteria);
        $response = parent::selectByRequest($criteria, $related);

        return static::cleanResult($response, array_get($criteria, 'select'));
    }

    /**
     * Removes 'config' from select criteria if supplied as it chokes the model.
     *
     * @param array $criteria
     *
     * @return array
     */
    protected static function cleanCriteria(array $criteria)
    {
        $fields = array_get($criteria, 'select');
        $criteria['select'] = static::cleanFields($fields);

        return $criteria;
    }

    /**
     * Removes unwanted fields from field list if supplied.
     *
     * @param mixed $fields
     *
     * @return array
     */
    public static function cleanFields($fields)
    {
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        return $fields;
    }

    /**
     * If fields is not '*' (all) then clean out any unwanted properties.
     *
     * @param mixed $response
     * @param mixed $fields
     *
     * @return array
     */
    protected static function cleanResult($response, /** @noinspection PhpUnusedParameterInspection */ $fields)
    {
        return $response;
    }
}