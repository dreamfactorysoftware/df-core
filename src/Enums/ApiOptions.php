<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * API Options
 * URL or payload options/parameters as string constants
 */
class ApiOptions extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const FIELDS = 'fields';
    /**
     * @var string
     */
    const PARAMS = 'params';
    /**
     * @var string
     */
    const AS_LIST = 'as_list';
    /**
     * @var string
     */
    const AS_ACCESS_LIST = 'as_access_list';
    /**
     * @var string
     */
    const IDS = 'ids';
    /**
     * @var string
     */
    const ID_FIELD = 'id_field';
    /**
     * @var string
     */
    const ID_TYPE = 'id_type';
    /**
     * @var string
     */
    const CONTINUES = 'continue';
    /**
     * @var string
     */
    const ROLLBACK = 'rollback';
    /**
     * @var string
     */
    const RELATED = 'related';
    /**
     * @var string
     */
    const ALLOW_RELATED_DELETE = 'allow_related_delete';
    /**
     * @var string
     */
    const FILTER = 'filter';
    /**
     * @var string
     */
    const LIMIT = 'limit';
    /**
     * @var string
     */
    const OFFSET = 'offset';
    /**
     * @var string
     */
    const ORDER = 'order';
    /**
     * @var string
     */
    const GROUP = 'group';
    /**
     * @var string
     */
    const HAVING = 'having';
    /**
     * @var string
     */
    const FILE = 'file';
    /**
     * @var string
     */
    const INCLUDE_ACCESS = 'include_access';
    /**
     * @var string
     */
    const INCLUDE_COUNT = 'include_count';
    /**
     * @var string
     */
    const INCLUDE_SCHEMA = 'include_schema';
    /**
     * @var string
     */
    const REFRESH = 'refresh';
    /**
     * @var string
     */
    const REGENERATE = 'regenerate';
    /**
     * @var string
     */
    const SCHEMA = 'schema';
    /**
     * @var string
     */
    const FORCE = 'force';

    //*************************************************************************
    //	Common Option Values
    //*************************************************************************
    /**
     * @var string
     */
    const FIELDS_ALL = '*';

    protected static $aliasMap = [
        self::FIELDS => ['select'],
        self::FILTER => ['where'],
        self::LIMIT  => ['top'],
        self::OFFSET => ['skip'],
        self::ORDER  => ['sort', 'order_by'],
        self::GROUP  => ['group_by'],
    ];

    protected static $swaggerMap = [
        self::IDS                  => [
            'name'        => self::IDS,
            'type'        => 'array',
            'items'       => [
                'type'   => 'integer',
                'format' => 'int32'
            ],
            'in'          => 'query',
            'description' => 'Comma-delimited list of the identifiers of the records to retrieve.',
        ],
        self::ID_FIELD             => [
            'name'        => self::ID_FIELD,
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'in'          => 'query',
            'description' => 'Comma-delimited list of the fields used as identifiers, used to override defaults or provide identifiers when none are provisioned.'
        ],
        self::ID_TYPE              => [
            'name'        => self::ID_TYPE,
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'in'          => 'query',
            'description' => 'Comma-delimited list of the field types used as identifiers for the table, used to override defaults or provide identifiers when none are provisioned.'
        ],
        self::FILTER               => [
            'name' => self::FILTER,
            'type'        => 'string',
            'in'          => 'query',
            'description' => 'SQL-like filter to limit the records to retrieve.'
        ],
        self::LIMIT                => [
            'name'        => self::LIMIT,
            'type'        => 'integer',
            'format'      => 'int32',
            'in'          => 'query',
            'description' => 'Set to limit the filter results.'
        ],
        self::ORDER                => [
            'name'        => self::ORDER,
            'type'        => 'string',
            'in'          => 'query',
            'description' => 'SQL-like order containing field and direction for filter results.'
        ],
        self::GROUP                => [
            'name'        => self::GROUP,
            'type'        => 'string',
            'in'          => 'query',
            'description' => 'Comma-delimited list of the fields used for grouping of filter results.'
        ],
        self::HAVING               => [
            'name'        => self::HAVING,
            'type'        => 'string',
            'in'          => 'query',
            'description' => 'SQL-like filter to limit the results after the grouping of filter results.'
        ],
        self::OFFSET               => [
            'name'        => self::OFFSET,
            'type'        => 'integer',
            'format'      => 'int32',
            'in'          => 'query',
            'description' => 'Set to offset the filter results to a particular record count.'
        ],
        self::FIELDS               => [
            'name'        => self::FIELDS,
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'in'          => 'query',
            'description' => 'Comma-delimited list of properties to be returned for each resource, "*" returns all properties. If as_list, use this to override the default identifier.'
        ],
        self::CONTINUES            => [
            'name'        => self::CONTINUES,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'In batch scenarios where supported, continue processing even after one action fails. Default behavior is to halt and return results up to the first point of failure.'
        ],
        self::ROLLBACK             => [
            'name'        => self::ROLLBACK,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'In batch scenarios where supported, rollback all actions if one action fails. Default behavior is to halt and return results up to the first point of failure.'
        ],
        self::RELATED              => [
            'name'        => self::RELATED,
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'in'          => 'query',
            'description' => 'Comma-delimited list of related names to retrieve for each resource.'
        ],
        self::ALLOW_RELATED_DELETE => [
            'name'        => self::ALLOW_RELATED_DELETE,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Set to true to allow related records to be deleted on parent update.'
        ],
        self::INCLUDE_ACCESS       => [
            'name'        => self::INCLUDE_ACCESS,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Include the access permissions for the returned resource.'
        ],
        self::INCLUDE_COUNT        => [
            'name'        => self::INCLUDE_COUNT,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Include the total number of filter results in returned metadata.'
        ],
        self::INCLUDE_SCHEMA       => [
            'name'        => self::INCLUDE_SCHEMA,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Include the schema of the table queried in returned metadata.'
        ],
        self::AS_LIST              => [
            'name'        => self::AS_LIST,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Return only a list of the resource identifiers.'
        ],
        self::AS_ACCESS_LIST       => [
            'name'        => self::AS_ACCESS_LIST,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Returns a list of the resources for role access designation.'
        ],
        self::REFRESH              => [
            'name'        => self::REFRESH,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Refresh any cached resource list on the server.'
        ],
        self::REGENERATE           => [
            'name'        => self::SCHEMA,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Generate a new API key for this application.'
        ],
        self::FORCE                => [
            'name'        => self::FORCE,
            'type'        => 'boolean',
            'in'          => 'query',
            'description' => 'Set to true to delete all resources in the given table, folder, etc.'
        ],
        self::FILE                 => [
            'name'        => self::FILE,
            'type'        => 'string',
            'in'          => 'query',
            'description' => 'Download the results of the request as a file.'
        ],
        self::SCHEMA               => [
            'name'        => self::SCHEMA,
            'type'        => 'string',
            'in'          => 'query',
            'description' => 'Select only a single schema of a database. Not applicable on all database services.'
        ],
    ];

    public static function documentOption($option, $required = false, $default = null)
    {
        if (isset(static::$swaggerMap[$option])) {
            $found = static::$swaggerMap[$option];
            $found['name'] = $option;
            if ($required) {
                $found['required'] = true;
            }
            if (isset($default)) {
                $found['default'] = $default;
            }

            return $found;
        }

        return null;
    }

    public static function checkArray($option, $params, $default = null)
    {
        if (is_array($params)) {
            if (array_key_exists($option, $params)) {
                return $params[$option];
            }
            if (isset(static::$aliasMap[$option])) {
                foreach (static::$aliasMap[$option] as $alias) {
                    if (array_key_exists($alias, $params)) {
                        return $params[$alias];
                    }
                }
            }
        }

        return $default;
    }

    public static function checkRequest(
        $option,
        ServiceRequestInterface $request,
        $default = null,
        $checkPayload = false
    ){
        $checkBool = ('boolean' === static::getType($option));
        $value = ($checkBool) ? $request->getParameterAsBool($option) : $request->getParameter($option);
        if (!is_null($value)) {
            return $value;
        }

        if ($checkPayload) {
            $value = $request->getPayloadData($option);
            if (!is_null($value)) {
                return ($checkBool) ? boolval($value) : $value;
            }
        }

        return $default;
    }

    public static function getAliases($option)
    {
        if (isset(static::$aliasMap[$option])) {
            return static::$aliasMap[$option];
        }

        return [];
    }

    public static function getType($option)
    {
        if (isset(static::$swaggerMap[$option], static::$swaggerMap[$option]['type'])) {
            return static::$swaggerMap[$option]['type'];
        }

        return 'string';
    }
}
