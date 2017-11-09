<?php

namespace DreamFactory\Core\Enums;

use DreamFactory\Core\Contracts\ServiceRequestInterface;

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
    const COUNT_ONLY = 'count_only';
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
    /**
     * @var string
     */
    const SEND_INVITE = 'send_invite';

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

    protected static $optionMap = [
        self::IDS                  => [
            'type'        => 'int[]',
            'description' => 'Comma-delimited list of the identifiers of the records to retrieve.',
        ],
        self::ID_FIELD             => [
            'type'        => 'string[]',
            'description' => 'Comma-delimited list of the fields used as identifiers, used to override defaults or provide identifiers when none are provisioned.'
        ],
        self::ID_TYPE              => [
            'type'        => 'string[]',
            'description' => 'Comma-delimited list of the field types used as identifiers for the table, used to override defaults or provide identifiers when none are provisioned.'
        ],
        self::FILTER               => [
            'type'        => 'string',
            'description' => 'SQL-like filter to limit the records to retrieve.'
        ],
        self::LIMIT                => [
            'type'        => 'int',
            'description' => 'Set to limit the filter results.'
        ],
        self::ORDER                => [
            'type'        => 'string',
            'description' => 'SQL-like order containing field and direction for filter results.'
        ],
        self::GROUP                => [
            'type'        => 'string',
            'description' => 'Comma-delimited list of the fields used for grouping of filter results.'
        ],
        self::HAVING               => [
            'type'        => 'string',
            'description' => 'SQL-like filter to limit the results after the grouping of filter results.'
        ],
        self::OFFSET               => [
            'type'        => 'int',
            'description' => 'Set to offset the filter results to a particular record count.'
        ],
        self::FIELDS               => [
            'type'        => 'string[]',
            'description' => 'Comma-delimited list of properties to be returned for each resource, "*" returns all properties. If as_list, use this to override the default identifier.'
        ],
        self::CONTINUES            => [
            'type'        => 'boolean',
            'description' => 'In batch scenarios where supported, continue processing even after one action fails. Default behavior is to halt and return results up to the first point of failure.'
        ],
        self::ROLLBACK             => [
            'type'        => 'boolean',
            'description' => 'In batch scenarios where supported, rollback all actions if one action fails. Default behavior is to halt and return results up to the first point of failure.'
        ],
        self::RELATED              => [
            'type'        => 'string[]',
            'description' => 'Comma-delimited list of related names to retrieve for each resource.'
        ],
        self::ALLOW_RELATED_DELETE => [
            'type'        => 'boolean',
            'description' => 'Set to true to allow related records to be deleted on parent update.'
        ],
        self::INCLUDE_ACCESS       => [
            'type'        => 'boolean',
            'description' => 'Include the access permissions for the returned resource.'
        ],
        self::COUNT_ONLY           => [
            'type'        => 'boolean',
            'description' => 'Return only the total number of filter results.'
        ],
        self::INCLUDE_COUNT        => [
            'type'        => 'boolean',
            'description' => 'Include the total number of filter results in returned metadata.'
        ],
        self::INCLUDE_SCHEMA       => [
            'type'        => 'boolean',
            'description' => 'Include the schema of the table queried in returned metadata.'
        ],
        self::AS_LIST              => [
            'type'        => 'boolean',
            'description' => 'Return only a list of the resource identifiers.'
        ],
        self::AS_ACCESS_LIST       => [
            'type'        => 'boolean',
            'description' => 'Returns a list of the resources for role access designation.'
        ],
        self::REFRESH              => [
            'type'        => 'boolean',
            'description' => 'Refresh any cached resource list on the server.'
        ],
        self::REGENERATE           => [
            'type'        => 'boolean',
            'description' => 'Generate a new API key for this application.'
        ],
        self::FORCE                => [
            'type'        => 'boolean',
            'description' => 'Set to true to delete all resources in the given table, folder, etc.'
        ],
        self::FILE                 => [
            'type'        => 'string',
            'description' => 'Download the results of the request as a file. **This is here for documentation purpose only. File will not download using API Docs.**'
        ],
        self::SCHEMA               => [
            'type'        => 'string',
            'description' => 'Select only a single schema of a database. Not applicable on all database services.'
        ],
        self::SEND_INVITE          => [
            'type'        => 'boolean',
            'description' => 'Send email invite to user.'
        ],
    ];

    public static function documentOption($option, $required = false)
    {
        if (isset(static::$optionMap[$option])) {
            $found = static::$optionMap[$option];
            $found['name'] = $option;
            $found['in'] = 'query';
            $found['style'] = 'form';
            $found['explode'] = false;
            $type = array_get($found, 'type', 'string');
            unset($found['type']);
            switch ($type) {
                case 'int[]':
                    $found['schema'] = [
                        'type'  => 'array',
                        'items' => [
                            'type'   => 'integer',
                            'format' => 'int32'
                        ],
                    ];
                    break;
                case 'string[]':
                    $found['schema'] = [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ];
                    break;
                case 'int':
                    $found['schema'] = ['type' => 'integer', 'format' => 'int32'];
                    break;
                default:
                    $found['schema'] = ['type' => $type];
                    break;
            }

            // override, do not reference
            if ($required) {
                $found['required'] = true;
            }

            return $found;
//            } else {
//                return ['$ref' => '#/parameters/' . $option];
//            }
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
    ) {
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
        if (isset(static::$optionMap[$option], static::$optionMap[$option]['type'])) {
            return static::$optionMap[$option]['type'];
        }

        return 'string';
    }
}
