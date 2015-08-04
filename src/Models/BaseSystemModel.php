<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Library\Utility\ArrayUtils;

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

        return static::cleanResult($response, ArrayUtils::get($criteria, 'select'));
    }

    /**
     * {@inheritdoc}
     */
    public static function selectByRequest(array $criteria = [], array $related = [])
    {
        $criteria = static::cleanCriteria($criteria);
        $response = parent::selectByRequest($criteria, $related);

        return static::cleanResult($response, ArrayUtils::get($criteria, 'select'));
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
        $fields = ArrayUtils::get($criteria, 'select');
        ArrayUtils::set($criteria, 'select', static::cleanFields($fields));

        return $criteria;
    }

    /**
     * Removes 'config' from field list if supplied as it chokes the model.
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

        //If config is requested add id and type as they are need to pull config.
        if (in_array('config', $fields)) {
            $fields[] = 'id';
            $fields[] = 'type';
        }

        //Removing config from field list as it is not a real column in the table.
        if (in_array('config', $fields)) {
            $key = array_keys($fields, 'config');
            unset($fields[$key[0]]);
        }

        return $fields;
    }

    /**
     * If fields is not '*' (all) then remove the empty 'config' property.
     *
     * @param array $response
     * @param mixed $fields
     *
     * @return array
     */
    protected static function cleanResult(array $response, $fields)
    {
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        //config is only available when both id and type is present. Therefore only show config if id and type is there.
        if (ArrayUtils::get($fields, 0) !== '*' && (!in_array('type', $fields) || !in_array('id', $fields))) {
            $result = [];

            if (ArrayUtils::isArrayNumeric($response)) {
                foreach ($response as $r) {
                    if (isset($r['config'])) {
                        unset($r['config']);
                    }
                    $result[] = $r;
                }
            } else {
                foreach ($response as $k => $v) {
                    if ('config' === $k) {
                        unset($response[$k]);
                    }
                }
                $result = $response;
            }

            return $result;
        }

        return $response;
    }
}