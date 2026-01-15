<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DbComparisonOperators;
use DreamFactory\Core\Enums\DbLogicalOperators;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\DataFormatter;
use DreamFactory\Core\Utility\Session as SessionUtility;
use Config;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Utility\Session;

/**
 * Class DbRequestCriteria
 *
 * @package DreamFactory\Core\Components
 */
trait DbRequestCriteria
{
    /** @var  ServiceRequestInterface */
//    protected $request; // Not liked by PHP 5.6 - redeclaration warning

    protected function getMaxRecordsReturnedLimit()
    {
        // some classes define their own default
        $default = defined('static::MAX_RECORDS_RETURNED') ? static::MAX_RECORDS_RETURNED : 1000;

        return intval(\Config::get('database.max_records_returned', $default));
    }

    /**
     * Builds the selection criteria from request and returns it.
     *
     * @return array
     */
    protected function getSelectionCriteria()
    {
        $options = $this->request->getParameters();
        $payload = $this->getPayloadData();
        $options = array_merge($options, $payload);
        $this->request->setParameters($options);

        $criteria = [
            'params' => []
        ];

        if (null !== ($value = $this->request->getParameter(ApiOptions::FIELDS))) {
            $criteria['select'] = explode(',', $value);
        } else {
            $criteria['select'] = ['*'];
        }

        if (null !== ($value = $this->request->getPayloadData(ApiOptions::PARAMS))) {
            $criteria['params'] = $value;
        }

        if (null !== ($value = $this->request->getParameter(ApiOptions::FILTER))) {
            /** @type TableSchema $schema */
            /** @noinspection PhpUndefinedMethodInspection */
            $schema = $this->getModel()->getTableSchema();
            $native = $this->convertFilterToNative($value, $criteria['params'], [], $schema->getColumns());
            $criteria['condition'] = $native['where'];
            if (is_array($native['params'])) {
                if (is_array($criteria['params'])) {
                    $criteria['params'] = array_merge($criteria['params'], $native['params']);
                } else {
                    $criteria['params'] = $native['params'];
                }
            }
            //	Add current user ID into parameter array if in condition, but not specified.
            if (false !== stripos($value, ':user_id')) {
                if (!isset($criteria['params'][':user_id'])) {
                    $criteria['params'][':user_id'] = SessionUtility::getCurrentUserId();
                }
            }
        }

        $value = intval($this->request->getParameter(ApiOptions::LIMIT));
        $maxAllowed = $this->getMaxRecordsReturnedLimit();
        if (($value < 1) || ($value > $maxAllowed)) {
            // impose a limit to protect server
            $value = $maxAllowed;
        }
        $criteria['limit'] = $value;

        // merge in possible payload options
        $optionNames = [
            ApiOptions::OFFSET,
            ApiOptions::ORDER,
            ApiOptions::GROUP,
        ];

        foreach ($optionNames as $option) {
            if (null !== $value = $this->request->getParameter($option)) {
                $criteria[$option] = $value;
            } elseif (!empty($otherNames = ApiOptions::getAliases($option))) {
                foreach ($otherNames as $other) {
                    if (null !== $value = $this->request->getParameter($other)) {
                        $criteria[$option] = $value;
                    } elseif (null !== $value = $this->request->getPayloadData($other)) {
                        $criteria[$option] = $value;
                    }
                }
            }
            if (!isset($criteria[$option]) && ((null !== $value = $this->request->getPayloadData($option)))) {
                $criteria[$option] = $value;
            }
        }

        return $criteria;
    }

    /**
     * Take in a ANSI SQL filter string (WHERE clause)
     * or our generic NoSQL filter array or partial record
     * and parse it to the service's native filter criteria.
     * The filter string can have substitution parameters such as
     * ':name', in which case an associative array is expected,
     * for value substitution.
     *
     * @param string | array $filter       SQL WHERE clause filter string
     * @param array          $params       Array of substitution values
     * @param array          $ss_filters   Server-side filters to apply
     * @param array          $avail_fields All available fields for the table
     *
     * @return mixed
     */
    protected function convertFilterToNative($filter, $params = [], $ss_filters = [], $avail_fields = [])
    {
        // interpret any parameter values as lookups
        $params = (is_array($params) ? static::interpretRecordValues($params) : []);
        $serverFilter = $this->buildQueryStringFromData($ss_filters);

        $outParams = [];
        if (empty($filter)) {
            $filter = $serverFilter;
        } elseif (is_string($filter)) {
            if (!empty($serverFilter)) {
                $filter = '(' . $filter . ') ' . DbLogicalOperators::AND_STR . ' (' . $serverFilter . ')';
            }
        } elseif (is_array($filter)) {
            // todo parse client filter?
            $filter = '';
            if (!empty($serverFilter)) {
                $filter = '(' . $filter . ') ' . DbLogicalOperators::AND_STR . ' (' . $serverFilter . ')';
            }
        }

        SessionUtility::replaceLookups($filter);
        $filterString = $this->parseFilterString($filter, $outParams, $avail_fields, $params);

        return ['where' => $filterString, 'params' => $outParams];
    }

    /**
     * @param       $filter_info
     *
     * @return null|string
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function buildQueryStringFromData($filter_info)
    {
        $filters = array_get($filter_info, 'filters');
        if (empty($filters)) {
            return null;
        }

        $sql = '';
        $combiner = array_get($filter_info, 'filter_op', DbLogicalOperators::AND_STR);
        foreach ($filters as $key => $filter) {
            if (!empty($sql)) {
                $sql .= " $combiner ";
            }

            $name = array_get($filter, 'name');
            $op = strtoupper(array_get($filter, 'operator'));
            if (empty($name) || empty($op)) {
                // log and bail
                throw new InternalServerErrorException('Invalid server-side filter configuration detected.');
            }

            if (DbComparisonOperators::requiresNoValue($op)) {
                $sql .= "($name $op)";
            } else {
                $value = array_get($filter, 'value');
                $sql .= "($name $op $value)";
            }
        }

        return $sql;
    }

    /**
     * @param string         $filter
     * @param array          $out_params
     * @param ColumnSchema[] $fields_info
     * @param array          $in_params
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    protected function parseFilterString($filter, array &$out_params, $fields_info, array $in_params = [])
    {
        if (empty($filter)) {
            return null;
        }

        $filter = trim($filter);
        // todo use smarter regex
        // handle logical operators first
        $logicalOperators = DbLogicalOperators::getDefinedConstants();
        foreach ($logicalOperators as $logicalOp) {
            if (DbLogicalOperators::NOT_STR === $logicalOp) {
                // NOT(a = 1)  or NOT (a = 1)format
                if ((0 === stripos($filter, $logicalOp . ' (')) || (0 === stripos($filter, $logicalOp . '('))) {
                    $parts = trim(substr($filter, 3));
                    $parts = $this->parseFilterString($parts, $out_params, $fields_info, $in_params);

                    return static::localizeOperator($logicalOp) . $parts;
                }
            } else {
                // (a = 1) AND (b = 2) format or (a = 1)AND(b = 2) format
                $filter = str_ireplace(')' . $logicalOp . '(', ') ' . $logicalOp . ' (', $filter);
                $paddedOp = ') ' . $logicalOp . ' (';
                if (false !== $pos = stripos($filter, $paddedOp)) {
                    $left = trim(substr($filter, 0, $pos)) . ')'; // add back right )
                    $right = '(' . trim(substr($filter, $pos + strlen($paddedOp))); // adding back left (
                    $left = $this->parseFilterString($left, $out_params, $fields_info, $in_params);
                    $right = $this->parseFilterString($right, $out_params, $fields_info, $in_params);

                    return $left . ' ' . static::localizeOperator($logicalOp) . ' ' . $right;
                }
            }
        }

        $wrap = false;
        if ((0 === strpos($filter, '(')) && ((strlen($filter) - 1) === strrpos($filter, ')'))) {
            // remove unnecessary wrapping ()
            $filter = substr($filter, 1, -1);
            $wrap = true;
        }

        // Some scenarios leave extra parens dangling
        $pure = trim($filter, '()');
        $pieces = explode($pure, $filter);
        $leftParen = (!empty($pieces[0]) ? $pieces[0] : null);
        $rightParen = (!empty($pieces[1]) ? $pieces[1] : null);
        $filter = $pure;

        // the rest should be comparison operators
        // Note: order matters here!
        $sqlOperators = DbComparisonOperators::getParsingOrder();
        foreach ($sqlOperators as $sqlOp) {
            $paddedOp = static::padOperator($sqlOp);
            if (false !== $pos = stripos($filter, $paddedOp)) {
                $field = trim(substr($filter, 0, $pos));
                $negate = false;
                if (false !== strpos($field, ' ')) {
                    $parts = explode(' ', $field);
                    $partsCount = count($parts);
                    if (($partsCount > 1) &&
                        (0 === strcasecmp($parts[$partsCount - 1], trim(DbLogicalOperators::NOT_STR)))
                    ) {
                        // negation on left side of operator
                        array_pop($parts);
                        $field = implode(' ', $parts);
                        $negate = true;
                    }
                }
                /** @type ColumnSchema $info */
                if (null === $info = array_get($fields_info, strtolower($field))) {
                    // This could be SQL injection attempt or bad field
                    throw new BadRequestException("Invalid or unparsable field in filter request: '$field'");
                }

                // make sure we haven't chopped off right side too much
                $value = trim(substr($filter, $pos + strlen($paddedOp)));
                if ((0 !== strpos($value, "'")) &&
                    (0 !== $lpc = substr_count($value, '(')) &&
                    ($lpc !== $rpc = substr_count($value, ')'))
                ) {
                    // add back to value from right
                    $parenPad = str_repeat(')', $lpc - $rpc);
                    $value .= $parenPad;
                    $rightParen = preg_replace('/\)/', '', $rightParen, $lpc - $rpc);
                }
                if (DbComparisonOperators::requiresValueList($sqlOp)) {
                    if ((0 === strpos($value, '(')) && ((strlen($value) - 1) === strrpos($value, ')'))) {
                        // remove wrapping ()
                        $value = substr($value, 1, -1);
                        $parsed = [];
                        foreach (explode(',', $value) as $each) {
                            $parsed[] = $this->parseFilterValue(trim($each), $info, $out_params, $in_params);
                        }
                        $value = '(' . implode(',', $parsed) . ')';
                    } else {
                        throw new BadRequestException('Filter value lists must be wrapped in parentheses.');
                    }
                } elseif (DbComparisonOperators::requiresNoValue($sqlOp)) {
                    $value = null;
                } else {
                    static::modifyValueByOperator($sqlOp, $value);
                    $value = $this->parseFilterValue($value, $info, $out_params, $in_params);
                }

                $sqlOp = static::localizeOperator($sqlOp);
                if ($negate) {
                    $sqlOp = DbLogicalOperators::NOT_STR . ' ' . $sqlOp;
                }

                $out = $info->quotedName . " $sqlOp";
                $out .= (isset($value) ? " $value" : null);
                if ($leftParen) {
                    $out = $leftParen . $out;
                }
                if ($rightParen) {
                    $out .= $rightParen;
                }

                return ($wrap ? '(' . $out . ')' : $out);
            }
        }

        // This could be SQL injection attempt or unsupported filter arrangement
        throw new BadRequestException('Invalid or unparsable filter request.');
    }

    /**
     * @param mixed        $value
     * @param ColumnSchema $info
     * @param array        $out_params
     * @param array        $in_params
     *
     * @return int|null|string
     * @throws BadRequestException
     */
    protected function parseFilterValue($value, ColumnSchema $info, array &$out_params, array $in_params = [])
    {
        // if a named replacement parameter, un-name it because Laravel can't handle named parameters
        if (is_array($in_params) && (0 === strpos($value, ':'))) {
            if (array_key_exists($value, $in_params)) {
                $value = $in_params[$value];
            }
        }

        // remove quoting on strings if used, i.e. 1.x required them
        if (is_string($value)) {

            if ((0 === strcmp("'" . trim($value, "'") . "'", $value)) ||
                (0 === strcmp('"' . trim($value, '"') . '"', $value))
            ) {
                $value = substr($value, 1, -1);
            } elseif ((0 === strpos($value, '(')) && ((strlen($value) - 1) === strrpos($value, ')'))) {
                // function call
                return $value;
            }
        }
        // if not already a replacement parameter, evaluate it
//            $value = $this->dbConn->getSchema()->parseValueForSet($value, $info);

        switch ($info->type) {
            case DbSimpleTypes::TYPE_BOOLEAN:
                break;

            case DbSimpleTypes::TYPE_INTEGER:
            case DbSimpleTypes::TYPE_ID:
            case DbSimpleTypes::TYPE_REF:
            case DbSimpleTypes::TYPE_USER_ID:
            case DbSimpleTypes::TYPE_USER_ID_ON_CREATE:
            case DbSimpleTypes::TYPE_USER_ID_ON_UPDATE:
                if (!is_int($value)) {
                    if (!(ctype_digit($value))) {
                        throw new BadRequestException("Field '{$info->getName(true)}' must be a valid integer.");
                    } else {
                        $value = intval($value);
                    }
                }
                break;

            case DbSimpleTypes::TYPE_DECIMAL:
            case DbSimpleTypes::TYPE_DOUBLE:
            case DbSimpleTypes::TYPE_FLOAT:
                break;

            case DbSimpleTypes::TYPE_STRING:
            case DbSimpleTypes::TYPE_TEXT:
                break;

            // special checks
            case DbSimpleTypes::TYPE_DATE:
                $cfgFormat = Config::get('df.db.date_format');
                $outFormat = 'Y-m-d';
                $value = DataFormatter::formatDateTime($outFormat, $value, $cfgFormat);
                break;

            case DbSimpleTypes::TYPE_TIME:
                $cfgFormat = Config::get('df.db.time_format');
                $outFormat = 'H:i:s.u';
                $value = DataFormatter::formatDateTime($outFormat, $value, $cfgFormat);
                break;

            case DbSimpleTypes::TYPE_DATETIME:
                $cfgFormat = Config::get('df.db.datetime_format');
                $outFormat = 'Y-m-d H:i:s';
                $value = DataFormatter::formatDateTime($outFormat, $value, $cfgFormat);
                break;

            case DbSimpleTypes::TYPE_TIMESTAMP:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                $cfgFormat = Config::get('df.db.timestamp_format');
                $outFormat = 'Y-m-d H:i:s';
                $value = DataFormatter::formatDateTime($outFormat, $value, $cfgFormat);
                break;

            default:
                break;
        }

        $out_params[] = $value;
        $value = '?';

        return $value;
    }

    public static function padOperator($operator)
    {
        if (ctype_alpha($operator)) {
            if (DbComparisonOperators::requiresNoValue($operator)) {
                return ' ' . $operator;
            }

            return ' ' . $operator . ' ';
        }

        return $operator;
    }

    public static function localizeOperator($operator)
    {
        switch ($operator) {
            // Logical
            case DbLogicalOperators::AND_SYM:
                return DbLogicalOperators::AND_STR;
            case DbLogicalOperators::OR_SYM:
                return DbLogicalOperators::OR_STR;
            // Comparison
            case DbComparisonOperators::EQ_STR:
                return DbComparisonOperators::EQ;
            case DbComparisonOperators::NE_STR:
                return DbComparisonOperators::NE;
            case DbComparisonOperators::NE_2:
                return DbComparisonOperators::NE;
            case DbComparisonOperators::GT_STR:
                return DbComparisonOperators::GT;
            case DbComparisonOperators::GTE_STR:
                return DbComparisonOperators::GTE;
            case DbComparisonOperators::LT_STR:
                return DbComparisonOperators::LT;
            case DbComparisonOperators::LTE_STR:
                return DbComparisonOperators::LTE;
            // Value-Modifying Operators
            case DbComparisonOperators::CONTAINS:
            case DbComparisonOperators::STARTS_WITH:
            case DbComparisonOperators::ENDS_WITH:
                return DbComparisonOperators::LIKE;
            default:
                return $operator;
        }
    }

    public static function modifyValueByOperator($operator, &$value)
    {
        switch ($operator) {
            // Value-Modifying Operators
            case DbComparisonOperators::CONTAINS:
                $value = '%' . $value . '%';
                break;
            case DbComparisonOperators::STARTS_WITH:
                $value = $value . '%';
                break;
            case DbComparisonOperators::ENDS_WITH:
                $value = '%' . $value;
                break;
        }
    }

    /**
     * @param $value
     *
     * @return bool|int|null|string
     */
    public static function interpretFilterValue($value)
    {
        // all other data types besides strings, just return
        if (!is_string($value) || empty($value)) {
            return $value;
        }

        $end = strlen($value) - 1;
        // filter string values should be wrapped in matching quotes
        if (((0 === strpos($value, '"')) && ($end === strrpos($value, '"'))) ||
            ((0 === strpos($value, "'")) && ($end === strrpos($value, "'")))
        ) {
            return substr($value, 1, $end - 1);
        }

        // check for boolean or null values
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        if (is_numeric($value)) {
            return $value + 0; // trick to get int or float
        }

        // the rest should be lookup keys, or plain strings
        SessionUtility::replaceLookups($value);

        return $value;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public static function interpretRecordValues($record)
    {
        if (!is_array($record) || empty($record)) {
            return $record;
        }

        foreach ($record as $field => $value) {
            SessionUtility::replaceLookups($value);
            $record[$field] = $value;
        }

        return $record;
    }
}