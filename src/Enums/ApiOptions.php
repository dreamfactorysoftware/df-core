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
    const FILE = 'file';
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

    public static $aliasMap = [
        self::FIELDS => ['select'],
        self::FILTER => ['where'],
        self::LIMIT  => ['top'],
        self::OFFSET => ['skip'],
        self::ORDER  => ['sort'],
    ];

    public static $typeMap = [
        // only include non-strings here for speed
        self::LIMIT          => 'integer',
        self::OFFSET         => 'integer',
        self::CONTINUES      => 'boolean',
        self::ROLLBACK       => 'boolean',
        self::INCLUDE_COUNT  => 'boolean',
        self::INCLUDE_SCHEMA => 'boolean',
        self::AS_LIST        => 'boolean',
        self::AS_ACCESS_LIST => 'boolean',
        self::REFRESH        => 'boolean',
        self::REGENERATE     => 'boolean',
    ];

    public static $descriptionMap = [
        self::IDS            => 'Comma-delimited list of the identifiers of the records to retrieve.',
        self::ID_FIELD       => 'Comma-delimited list of the fields used as identifiers, used to override defaults or provide identifiers when none are provisioned.',
        self::ID_TYPE        => 'Comma-delimited list of the field types used as identifiers for the table, used to override defaults or provide identifiers when none are provisioned.',
        self::FILTER         => 'SQL-like filter to limit the records to retrieve.',
        self::LIMIT          => 'Set to limit the filter results.',
        self::ORDER          => 'SQL-like order containing field and direction for filter results.',
        self::OFFSET         => 'Set to offset the filter results to a particular record count.',
        self::FIELDS         => 'Comma-delimited list of properties to be returned for each resource, "*" returns all properties. If as_list, use this to override the default identifier.',
        self::CONTINUES      => 'In batch scenarios where supported, continue processing even after one action fails. Default behavior is to halt and return results up to the first point of failure.',
        self::ROLLBACK       => 'In batch scenarios where supported, rollback all actions if one action fails. Default behavior is to halt and return results up to the first point of failure.',
        self::RELATED        => 'Comma-delimited list of related names to retrieve for each resource.',
        self::INCLUDE_COUNT  => 'Include the total number of filter results in returned metadata.',
        self::INCLUDE_SCHEMA => 'Include the schema of the table queried in returned metadata.',
        self::FILE           => 'Download the results of the request as a file.',
        self::AS_LIST        => 'Return only a list of the resource identifiers.',
        self::AS_ACCESS_LIST => 'Returns a list of the resources for role access designation.',
        self::REFRESH        => 'Refresh any cached resource list on the server.',
        self::REGENERATE     => 'Generate a new API key for this application.',
        self::SCHEMA         => 'Select only a single schema of a database. Not applicable on all database services.',
    ];

    public static $multipleMap = [
        // only put ones that allow multiple here
        self::IDS,
        self::FIELDS,
        self::RELATED,
    ];

    public static function documentOption($option, $required = false, $default = null)
    {
        return [
            'name'          => $option,
            'description'   => (isset(static::$descriptionMap[$option]) ? static::$descriptionMap[$option] : 'Unknown'),
            'allowMultiple' => in_array($option, static::$multipleMap),
            'type'          => (isset(static::$typeMap[$option]) ? static::$typeMap[$option] : 'string'),
            'format'        => 'int32',
            'paramType'     => 'query',
            'required'      => $required,
            'default'       => $default,
        ];
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
        $checkBool = (isset(static::$typeMap[$option]) && ('boolean' === static::$typeMap[$option]));
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
}
