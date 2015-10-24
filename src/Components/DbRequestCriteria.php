<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Database\ColumnSchema;
use DreamFactory\Core\Database\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\DbUtilities;
use DreamFactory\Core\Utility\Session as SessionUtility;
use DreamFactory\Library\Utility\ArrayUtils;

/**
 * Class DbRequestCriteria
 *
 * @package DreamFactory\Core\Components
 */
trait DbRequestCriteria
{
    protected function getMaxRecordsReturned()
    {
        // some classes define their own default
        $default = defined('static::MAX_RECORDS_RETURNED') ? static::MAX_RECORDS_RETURNED : 1000;

        return intval(\Config::get('df.db_max_records_returned', $default));
    }

    /**
     * Builds the selection criteria from request and returns it.
     *
     * @return array
     */
    protected function getSelectionCriteria()
    {
        /** @type TableSchema $schema */
        $schema = $this->getModel()->getTableSchema();

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
            $this->convertFilterToNative($value, $criteria['params'], [], $schema->columns);
            $criteria['condition'] = $value;

            //	Add current user ID into parameter array if in condition, but not specified.
            if (false !== stripos($value, ':user_id')) {
                if (!isset($criteria['params'][':user_id'])) {
                    $criteria['params'][':user_id'] = SessionUtility::getCurrentUserId();
                }
            }
        }

        $value = intval($this->request->getParameter(ApiOptions::LIMIT));
        $maxAllowed = $this->getMaxRecordsReturned();
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
        $params = ArrayUtils::clean(static::interpretRecordValues($params));

        if (!is_array($filter)) {
            SessionUtility::replaceLookups($filter);
            $filterString = '';
            $clientFilter = $this->parseFilterString($filter, $params, $avail_fields);
            if (!empty($clientFilter)) {
                $filterString = $clientFilter;
            }
            $serverFilter = $this->buildQueryStringFromData($ss_filters, $params);
            if (!empty($serverFilter)) {
                if (empty($filterString)) {
                    $filterString = $serverFilter;
                } else {
                    $filterString = '(' . $filterString . ') AND (' . $serverFilter . ')';
                }
            }

            return ['where' => $filterString, 'params' => $params];
        } else {
            // todo parse client filter?
            $filterArray = $filter;
            $serverFilter = $this->buildQueryStringFromData($ss_filters, $params);
            if (!empty($serverFilter)) {
                if (empty($filter)) {
                    $filterArray = $serverFilter;
                } else {
                    $filterArray = ['AND', $filterArray, $serverFilter];
                }
            }

            return ['where' => $filterArray, 'params' => $params];
        }
    }

    /**
     * @param       $filter_info
     * @param array $params
     *
     * @return null|string
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function buildQueryStringFromData($filter_info, array &$params)
    {
        $filter_info = ArrayUtils::clean($filter_info);
        $filters = ArrayUtils::get($filter_info, 'filters');
        if (empty($filters)) {
            return null;
        }

        $sql = '';
        $combiner = ArrayUtils::get($filter_info, 'filter_op', 'and');
        foreach ($filters as $key => $filter) {
            if (!empty($sql)) {
                $sql .= " $combiner ";
            }

            $name = ArrayUtils::get($filter, 'name');
            $op = ArrayUtils::get($filter, 'operator');
            $value = ArrayUtils::get($filter, 'value');
            $value = static::interpretFilterValue($value);

            if (empty($name) || empty($op)) {
                // log and bail
                throw new InternalServerErrorException('Invalid server-side filter configuration detected.');
            }

            switch ($op) {
                case 'is null':
                case 'is not null':
                    $sql .= "$name $op";
//                    $sql .= $this->dbConn->quoteColumnName($name) . " $op";
                    break;
                default:
                    $paramName = ':ssf_' . $name . '_' . $key;
                    $params[$paramName] = $value;
                    $value = $paramName;
                    $sql .= "$name $op $value";
//                    $sql .= $this->dbConn->quoteColumnName($name) . " $op $value";
                    break;
            }
        }

        return $sql;
    }

    /**
     * @param  string         $filter
     * @param  array          $params
     * @param  ColumnSchema[] $fields_info
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    protected function parseFilterString($filter, array &$params, $fields_info)
    {
        if (empty($filter)) {
            return null;
        }

        $search = [' or ', ' and ', ' nor '];
        $replace = [' OR ', ' AND ', ' NOR '];
        $filter = trim(str_ireplace($search, $replace, $filter));

        // handle logical operators first
        $ops = array_map('trim', explode(' OR ', $filter));
        if (count($ops) > 1) {
            $parts = [];
            foreach ($ops as $op) {
                $parts[] = static::parseFilterString($op, $params, $fields_info);
            }

            return implode(' OR ', $parts);
        }

        $ops = array_map('trim', explode(' NOR ', $filter));
        if (count($ops) > 1) {
            $parts = [];
            foreach ($ops as $op) {
                $parts[] = static::parseFilterString($op, $params, $fields_info);
            }

            return implode(' NOR ', $parts);
        }

        $ops = array_map('trim', explode(' AND ', $filter));
        if (count($ops) > 1) {
            $parts = [];
            foreach ($ops as $op) {
                $parts[] = static::parseFilterString($op, $params, $fields_info);
            }

            return implode(' AND ', $parts);
        }

        $pure = trim($filter, '()');
        $pieces = explode($pure, $filter);
        $leftParen = (!empty($pieces[0]) ? $pieces[0] : null);
        $rightParen = (!empty($pieces[1]) ? $pieces[1] : null);
        $filter = $pure;

        // the rest should be comparison operators
        $search = [' eq ', ' ne ', ' gte ', ' lte ', ' gt ', ' lt ', ' in ', ' all ', ' like ', ' <> '];
        $replace = ['=', '!=', '>=', '<=', '>', '<', ' IN ', ' ALL ', ' LIKE ', '!='];
        $filter = trim(str_ireplace($search, $replace, $filter));

        // Note: order matters here!
        $sqlOperators = ['!=', '>=', '<=', '=', '>', '<', ' IN ', ' ALL ', ' LIKE '];
        foreach ($sqlOperators as $sqlOp) {
            $ops = explode($sqlOp, $filter);
            switch (count($ops)) {
                case 2:
                    $field = trim($ops[0]);
                    $negate = false;
                    if (false !== strpos($field, ' ')) {
                        $parts = explode(' ', $field);
                        if ((count($parts) > 2) || (0 !== strcasecmp($parts[1], 'not'))) {
                            // invalid field side of operator
                            throw new BadRequestException('Invalid or unparsable field in filter request.');
                        }
                        $field = $parts[0];
                        $negate = true;
                    }
                    /** @type ColumnSchema $info */
                    if (null === $info = ArrayUtils::get($fields_info, strtolower($field))) {
                        // This could be SQL injection attempt or bad field
                        throw new BadRequestException('Invalid or unparsable field in filter request.');
                    }

                    $value = trim($ops[1]);
                    switch ($sqlOp) {
                        case ' IN ':
                        case ' ALL ':
                            $value = trim($value, '()[]');
                            $parsed = [];
                            foreach (explode(',', $value) as $each) {
                                $parsed[] = $this->parseFilterValue($each, $info, $params);
                            }
                            $value = '(' . implode(',', $parsed) . ')';
                            break;
                        default:
                            $value = $this->parseFilterValue($value, $info, $params);
                            break;
                    }

                    if ($negate) {
                        $sqlOp = 'NOT ' . $sqlOp;
                    }

                    $out = "{$info->rawName} $sqlOp $value";
                    if ($leftParen) {
                        $out = $leftParen . $out;
                    }
                    if ($rightParen) {
                        $out .= $rightParen;
                    }

                    return $out;
            }
        }

        if (0 === strcasecmp(' IS NULL', substr($filter, -8))) {
            $field = trim(substr($filter, 0, -8));
            /** @type ColumnSchema $info */
            if (null === $info = ArrayUtils::get($fields_info, strtolower($field))) {
                // This could be SQL injection attempt or bad field
                throw new BadRequestException('Invalid or unparsable field in filter request.');
            }

            $out = $info->rawName . ' IS NULL';
            if ($leftParen) {
                $out = $leftParen . $out;
            }
            if ($rightParen) {
                $out .= $rightParen;
            }

            return $out;
        }

        if (0 === strcasecmp(' IS NOT NULL', substr($filter, -12))) {
            $field = trim(substr($filter, 0, -12));
            /** @type ColumnSchema $info */
            if (null === $info = ArrayUtils::get($fields_info, strtolower($field))) {
                // This could be SQL injection attempt or bad field
                throw new BadRequestException('Invalid or unparsable field in filter request.');
            }

            $out = $info->rawName . ' IS NOT NULL';
            if ($leftParen) {
                $out = $leftParen . $out;
            }
            if ($rightParen) {
                $out .= $rightParen;
            }

            return $out;
        }

        // This could be SQL injection attempt or unsupported filter arrangement
        throw new BadRequestException('Invalid or unparsable filter request.');
    }

    /**
     * @param mixed        $value
     * @param ColumnSchema $info
     * @param array        $params
     *
     * @return int|null|string
     * @throws BadRequestException
     */
    protected function parseFilterValue($value, ColumnSchema $info, array &$params)
    {
        if (0 !== strpos($value, ':')) {
            // remove quoting on strings if used, i.e. 1.x required them
            if (is_string($value) &&
                ((0 === strcmp("'" . trim($value, "'") . "'", $value)) ||
                    (0 === strcmp('"' . trim($value, '"') . '"', $value)))
            ) {
                $value = trim($value, '"\'');
            }

            // if not already a replacement parameter, evaluate it
//            $value = $this->dbConn->getSchema()->parseValueForSet($value, $info);

            switch ($cnvType = DbUtilities::determinePhpConversionType($info->type)) {
                case 'int':
                    if (!is_int($value)) {
                        if (!(ctype_digit($value))) {
                            throw new BadRequestException("Field '{$info->getName(true)}' must be a valid integer.");
                        } else {
                            $value = intval($value);
                        }
                    }
                    break;

                case 'time':
                    $cfgFormat = \Config::get('df.db_time_format');
                    $outFormat = 'H:i:s.u';
                    $value = DbUtilities::formatDateTime($outFormat, $value, $cfgFormat);
                    break;
                case 'date':
                    $cfgFormat = \Config::get('df.db_date_format');
                    $outFormat = 'Y-m-d';
                    $value = DbUtilities::formatDateTime($outFormat, $value, $cfgFormat);
                    break;
                case 'datetime':
                    $cfgFormat = \Config::get('df.db_datetime_format');
                    $outFormat = 'Y-m-d H:i:s';
                    $value = DbUtilities::formatDateTime($outFormat, $value, $cfgFormat);
                    break;
                case 'timestamp':
                    $cfgFormat = \Config::get('df.db_timestamp_format');
                    $outFormat = 'Y-m-d H:i:s';
                    $value = DbUtilities::formatDateTime($outFormat, $value, $cfgFormat);
                    break;

                default:
                    break;
            }

            $paramName = ':cf_' . count($params); // positionally unique
            $params[$paramName] = $value;
            $value = $paramName;
        }

        return $value;
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