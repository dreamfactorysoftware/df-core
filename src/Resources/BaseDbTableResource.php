<?php

namespace DreamFactory\Core\Resources;

use Config;
use DreamFactory\Core\Components\DataValidator;
use DreamFactory\Core\Components\Service2ServiceRequest;
use DreamFactory\Core\Database\Schema\RelationSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DbComparisonOperators;
use DreamFactory\Core\Enums\DbLogicalOperators;
use DreamFactory\Core\Enums\DbResourceTypes;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Library\Utility\Scalar;
use ServiceManager;

abstract class BaseDbTableResource extends BaseDbResource
{
    use DataValidator;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Resource tag for dealing with table schema
     */
    const RESOURCE_NAME = '_table';
    /**
     * Replacement tag for dealing with table schema events
     */
    const EVENT_IDENTIFIER = '{table_name}';
    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var boolean
     */
    protected $useBlendFormat = true;
    /**
     * @var string
     */
    protected $transactionTable = null;
    /**
     * @var array
     */
    protected $tableFieldsInfo = [];
    /**
     * @var array
     */
    protected $tableIdsInfo = [];
    /**
     * @var array
     */
    protected $batchIds = [];
    /**
     * @var array
     */
    protected $batchRecords = [];
    /**
     * @var array
     */
    protected $rollbackRecords = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    public function getResourceName()
    {
        return static::RESOURCE_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function listResources($schema = null, $refresh = false)
    {
        /** @type TableSchema[] $result */
        $result = $this->parent->getSchema()->getResourceNames(DbResourceTypes::TYPE_TABLE, $schema, $refresh);
        $resources = [];
        foreach ($result as $table) {
            $name = $table->getName(true);
            if (!empty($this->getPermissions($name))) {
                $resources[] = $name;
            }
        }

        return $resources;
    }

    /**
     * @param null $schema
     * @param bool $refresh
     *
     * @return array
     */
    public function listAccessComponents($schema = null, $refresh = false)
    {
        $output = [];
        $result = $this->listResources($schema, $refresh);
        foreach ($result as $name) {
            $output[] = $this->getResourceName() . '/' . $name . '/';
            $output[] = $this->getResourceName() . '/' . $name . '/*';
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        if ($only_handlers) {
            return [];
        }

        $refresh = $this->request->getParameterAsBool(ApiOptions::REFRESH);
        $schema = $this->request->getParameter(ApiOptions::SCHEMA, '');
        /** @type TableSchema[] $result */
        $result = $this->parent->getSchema()->getResourceNames(DbResourceTypes::TYPE_TABLE, $schema, $refresh);
        $resources = [];
        foreach ($result as $table) {
            $access = $this->getPermissions($table->getName(true));
            if (!empty($access)) {
                $info = $table->toArray(true);
                $info['access'] = VerbsMask::maskToArray($access);
                $resources[] = $info;
            }
        }

        return $resources;
    }

    /**
     * @param string $name       The name of the table to check
     * @param bool   $returnName If true, the table name is returned instead of TRUE
     *
     * @throws \InvalidArgumentException
     * @return bool
     */
    public function doesTableExist($name, $returnName = false)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }

        //  Build the lower-cased table array
        /** @var TableSchema[] $result */
        $result = $this->parent->getSchema()->getResourceNames(DbResourceTypes::TYPE_TABLE);
        $tables = [];
        foreach ($result as $table) {
            $tables[strtolower($table->getName(true))] = $table;
        }

        //	Search normal, return real name
        $ndx = strtolower($name);
        if (isset($tables[$ndx])) {
            return $returnName ? $tables[$ndx]->name : true;
        }

        return false;
    }

    /**
     * {@InheritDoc}
     */
    protected function setResourceMembers($response_path = null)
    {
        $out = parent::setResourceMembers($response_path);

        $this->detectRequestMembers();

        return $out;
    }

    protected function getOptionalParameters()
    {
        return [
            ApiOptions::FIELDS,
            ApiOptions::IDS,
            ApiOptions::FILTER,
            ApiOptions::LIMIT,
            ApiOptions::OFFSET,
            ApiOptions::ORDER,
            ApiOptions::GROUP,
            ApiOptions::COUNT_ONLY,
            ApiOptions::PARAMS,
            ApiOptions::CONTINUES,
            ApiOptions::ROLLBACK
        ];
    }

    /**
     * {@InheritDoc}
     */
    protected function detectRequestMembers()
    {
        if (!empty($this->resource)) {
            $payload = $this->getPayloadData();
            $options = $this->request->getParameters();
            $optionNames = $this->getOptionalParameters();
            $updateOptions = false;

            // merge in possible payload options
            foreach ($optionNames as $key => $value) {
                if (!array_key_exists($value, $options)) {
                    if (array_key_exists($value, $payload)) {
                        $updateOptions = true;
                        $options[$value] = $payload[$value];
                    } elseif (!empty($otherNames = ApiOptions::getAliases($value))) {
                        foreach ($otherNames as $other) {
                            if (!array_key_exists($other, $options)) {
                                if (array_key_exists($other, $payload)) {
                                    $updateOptions = true;
                                    $options[$value] = $payload[$other];
                                }
                            } else {
                                $updateOptions = true;
                                $options[$value] = $options[$other];
                            }
                        }
                    }
                }
            }

            // set defaults if not present
            if (Verbs::GET == $this->request->getMethod()) {
                // default for GET should be "return all fields"
                if (!array_key_exists(ApiOptions::FIELDS, $options)) {
                    $updateOptions = true;
                    $options[ApiOptions::FIELDS] = '*';
                }
            }

            // Add server side filtering properties
            $resource = $this->name . '/' . $this->resource;
            if (null !=
                $ssFilters = Session::getServiceFilters($this->getRequestedAction(), $this->parent->name, $resource)
            ) {
                $updateOptions = true;
                $options['ss_filters'] = $ssFilters;
            }

            if ($updateOptions) {
                $this->request->setParameters($options);
            }

            // All calls can request related data to be returned
            $related = $this->request->getParameter(ApiOptions::RELATED);
            if (!empty($related) && is_string($related) && ('*' !== $related)) {
                if (!is_array($related)) {
                    $related = array_map('trim', explode(',', $related));
                }
                $relations = [];
                foreach ($related as $relative) {
                    // search for relation + '_' + option because '.' is replaced by '_'
                    $relations[strtolower($relative)] =
                        [
                            'name'             => $relative,
                            ApiOptions::FIELDS => $this->request->getParameter(
                                str_replace('.', '_', $relative . '.' . ApiOptions::FIELDS),
                                '*'),
                            ApiOptions::LIMIT  => $this->request->getParameter(
                                str_replace('.', '_', $relative . '.' . ApiOptions::LIMIT),
                                static::getMaxRecordsReturnedLimit()),
                            ApiOptions::ORDER  => $this->request->getParameter(
                                str_replace('.', '_', $relative . '.' . ApiOptions::ORDER)),
                            ApiOptions::GROUP  => $this->request->getParameter(
                                str_replace('.', '_', $relative . '.' . ApiOptions::GROUP)),
                        ];
                }

                $this->request->setParameter(ApiOptions::RELATED, $relations);
            }
        }

        return $this;
    }

    /**
     * @param string $table
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateTableAccess($table, $action = null)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        $this->checkPermission($action, $table);
    }

    protected function getEventName()
    {
        $suffix = '';
        switch (count($this->resourceArray)) {
            case 1:
                $suffix = '.' . static::EVENT_IDENTIFIER;
                break;
            case 2:
                $suffix = '.' . static::EVENT_IDENTIFIER . '.{id}';
                break;
            default:
                break;
        }

        return parent::getEventName() . $suffix;
    }


    protected function firePreProcessEvent($name = null, $resource = null)
    {
        // fire default first
        // Try the generic table event
        parent::firePreProcessEvent($name, $resource);

        // also fire more specific event
        // Try the actual table name event
        switch (count($this->resourceArray)) {
            case 1:
                parent::firePreProcessEvent(str_replace(static::EVENT_IDENTIFIER, $this->resourceArray[0],
                    $this->getEventName()), $resource);
                break;
            case 2:
                parent::firePreProcessEvent(str_replace(static::EVENT_IDENTIFIER, $this->resourceArray[0],
                    $this->getEventName()), $this->resourceArray[1]);
                break;
        }
    }

    protected function firePostProcessEvent($name = null, $resource = null)
    {
        // fire default first
        // Try the generic table event
        parent::firePostProcessEvent($name, $resource);

        // also fire more specific event
        // Try the actual table name event
        switch (count($this->resourceArray)) {
            case 1:
                parent::firePostProcessEvent(str_replace(static::EVENT_IDENTIFIER, $this->resourceArray[0],
                    $this->getEventName()), $resource);
                break;
            case 2:
                parent::firePostProcessEvent(str_replace(static::EVENT_IDENTIFIER, $this->resourceArray[0],
                    $this->getEventName()), $this->resourceArray[1]);
                break;
        }
    }

    protected function fireFinalEvent($name = null, $resource = null)
    {
        // fire default first
        // Try the generic table event
        parent::fireFinalEvent($name, $resource);

        // also fire more specific event
        // Try the actual table name event
        switch (count($this->resourceArray)) {
            case 1:
                parent::fireFinalEvent(str_replace(static::EVENT_IDENTIFIER, $this->resourceArray[0],
                    $this->getEventName()), $resource);
                break;
            case 2:
                parent::fireFinalEvent(str_replace(static::EVENT_IDENTIFIER, $this->resourceArray[0],
                    $this->getEventName()), $this->resourceArray[1]);
                break;
        }
    }

    /**
     * @return array
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function handleGet()
    {
        if (empty($this->resource)) {
            return parent::handleGET();
        }

        if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
            throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
        }

        $options = $this->request->getParameters();

        if (!empty($this->resourceId)) {
            //	Single resource by ID
            $result = $this->retrieveRecordById($tableName, $this->resourceId, $options);

            return $result;
        }

        if (!empty($ids = array_get($options, ApiOptions::IDS))) {
            //	Multiple resources by ID
            $result = $this->retrieveRecordsByIds($tableName, $ids, $options);
        } elseif (!empty($records = ResourcesWrapper::unwrapResources($this->getPayloadData()))) {
            // passing records to have them updated with new or more values, id field required
            $result = $this->retrieveRecords($tableName, $records, $options);
        } else {
            $filter = array_get($options, ApiOptions::FILTER);
            $params = array_get($options, ApiOptions::PARAMS, []);

            $result = $this->retrieveRecordsByFilter($tableName, $filter, $params, $options);
        }

        $meta = array_get($result, 'meta');
        unset($result['meta']);

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @return bool|array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function handlePost()
    {
        if (empty($this->resource)) {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
            throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
        }

        if (!empty($this->resourceId)) {
            throw new BadRequestException('Create record by identifier not currently supported.');
        }

        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $options = $this->request->getParameters();
        $result = $this->createRecords($tableName, $records, $options);

        $meta = array_get($result, 'meta');
        unset($result['meta']);

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @return bool|array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function handlePUT()
    {
        if (empty($this->resource)) {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
            throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
        }

        $options = $this->request->getParameters();

        if (!empty($this->resourceId)) {
            return $this->updateRecordById($tableName, $this->getPayloadData(), $this->resourceId, $options);
        }

        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $ids = array_get($options, ApiOptions::IDS);

        if (!empty($ids)) {
            $record = array_get($records, 0, $records);

            $result = $this->updateRecordsByIds($tableName, $record, $ids, $options);
        } else {
            $filter = array_get($options, ApiOptions::FILTER);
            if (!empty($filter)) {
                $record = array_get($records, 0, $records);
                $params = array_get($options, ApiOptions::PARAMS, []);
                $result = $this->updateRecordsByFilter(
                    $tableName,
                    $record,
                    $filter,
                    $params,
                    $options
                );
            } else {
                $result = $this->updateRecords($tableName, $records, $options);
            }
        }

        $meta = array_get($result, 'meta');
        unset($result['meta']);

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @return bool|array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function handlePatch()
    {
        if (empty($this->resource)) {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
            throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
        }

        $options = $this->request->getParameters();

        if (!empty($this->resourceId)) {
            return $this->patchRecordById($tableName, $this->getPayloadData(), $this->resourceId, $options);
        }

        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $ids = array_get($options, ApiOptions::IDS);

        if (!empty($ids)) {
            $record = array_get($records, 0, $records);
            $result = $this->patchRecordsByIds($tableName, $record, $ids, $options);
        } else {
            $filter = array_get($options, ApiOptions::FILTER);
            if (!empty($filter)) {
                $record = array_get($records, 0, $records);
                $params = array_get($options, ApiOptions::PARAMS, []);
                $result = $this->patchRecordsByFilter(
                    $tableName,
                    $record,
                    $filter,
                    $params,
                    $options
                );
            } else {
                $result = $this->patchRecords($tableName, $records, $options);
            }
        }

        $meta = array_get($result, 'meta');
        unset($result['meta']);

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @return bool|array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function handleDelete()
    {
        if (empty($this->resource)) {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
            throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
        }

        $options = $this->request->getParameters();

        if (!empty($this->resourceId)) {
            return $this->deleteRecordById($tableName, $this->resourceId, $options);
        }

        $ids = array_get($options, ApiOptions::IDS);
        if (!empty($ids)) {
            $result = $this->deleteRecordsByIds($tableName, $ids, $options);
        } else {
            $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
            if (!empty($records)) {
                $result = $this->deleteRecords($tableName, $records, $options);
            } else {
                $filter = array_get($options, ApiOptions::FILTER);
                if (!empty($filter)) {
                    $params = array_get($options, ApiOptions::PARAMS, []);
                    $result = $this->deleteRecordsByFilter($tableName, $filter, $params, $options);
                } else {
                    if (!Scalar::boolval(array_get($options, ApiOptions::FORCE))) {
                        throw new BadRequestException('No filter or records given for delete request.');
                    }

                    return $this->truncateTable($tableName, $options);
                }
            }
        }

        $meta = array_get($result, 'meta');
        unset($result['meta']);

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    // Handle table record operations

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecords($table, $records, $extras = [])
    {
        $records = static::validateAsArray($records, null, true, 'The request contains no valid record sets.');

        $isSingle = (1 == count($records));
        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $rollback = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::ROLLBACK, false));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));
        if ($rollback && $continue) {
            throw new BadRequestException('Rollback and continue operations can not be requested at the same time.');
        }

        $this->initTransaction($table, $idFields, $idTypes, false);

        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        $out = [];
        $errors = [];
        try {
            foreach ($records as $index => $record) {
                try {
                    if (false === $id = static::checkForIds($record, $this->tableIdsInfo, $extras, true)) {
                        throw new BadRequestException("Required id field(s) not found in record $index: " .
                            print_r($record, true));
                    }

                    $result = $this->addToTransaction($record, $id, $extras, $rollback, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || $rollback || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                // operation performed, take output, override earlier
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be created.';
            }

            if ($rollback) {
                $this->rollbackTransaction();

                $msg .= " All changes rolled back.";
            }

            if ($ex instanceof RestException) {
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to create records in '$table'.\n$msg", null, null, $context);
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecord($table, $record, $extras = [])
    {
        $records = static::validateAsArray($record, null, true, 'The request contains no valid record fields.');

        $results = $this->createRecords($table, $records, $extras);

        return $results[0];
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecords($table, $records, $extras = [])
    {
        $records = static::validateAsArray($records, null, true, 'The request contains no valid record sets.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $isSingle = (1 == count($records));
        $rollback = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::ROLLBACK, false));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));
        if ($rollback && $continue) {
            throw new BadRequestException('Rollback and continue operations can not be requested at the same time.');
        }

        $this->initTransaction($table, $idFields, $idTypes);

        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        $out = [];
        $errors = [];
        try {
            foreach ($records as $index => $record) {
                try {
                    if (false === $id = static::checkForIds($record, $this->tableIdsInfo, $extras)) {
                        throw new BadRequestException("Required id field(s) not found in record $index: " .
                            print_r($record, true));
                    }

                    $result = $this->addToTransaction($record, $id, $extras, $rollback, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || $rollback || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be updated.';
            }

            if ($rollback) {
                $this->rollbackTransaction();

                $msg .= " All changes rolled back.";
            }

            if ($ex instanceof RestException) {
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to update records in '$table'.\n$msg", null, null,
                $context);
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecord($table, $record, $extras = [])
    {
        $records = static::validateAsArray($record, null, true, 'The request contains no valid record fields.');

        $results = $this->updateRecords($table, $records, $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordsByFilter($table, $record, $filter = null, $params = [], $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'There are no fields in the record.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);

        // slow, but workable for now, maybe faster than merging individuals
        $extras[ApiOptions::FIELDS] = '';
        $records = $this->retrieveRecordsByFilter($table, $filter, $params, $extras);
        unset($records['meta']);

        $fieldsInfo = $this->getFieldsInfo($table);
        $idsInfo = $this->getIdsInfo($table, $fieldsInfo, $idFields, $idTypes);
        if (empty($idsInfo)) {
            throw new InternalServerErrorException("Identifying field(s) could not be determined.");
        }

        $ids = static::recordsAsIds($records, $idsInfo);
        if (empty($ids)) {
            return [];
        }

        $extras[ApiOptions::FIELDS] = $fields;

        return $this->updateRecordsByIds($table, $record, $ids, $extras);
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws InternalServerErrorException
     * @throws BadRequestException
     * @throws RestException
     * @return array
     */
    public function updateRecordsByIds($table, $record, $ids, $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'There are no fields in the record.');
        $ids = static::validateAsArray($ids, ',', true, 'The request contains no valid identifiers.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $isSingle = (1 == count($ids));
        $rollback = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::ROLLBACK, false));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));
        if ($rollback && $continue) {
            throw new BadRequestException('Rollback and continue operations can not be requested at the same time.');
        }

        $this->initTransaction($table, $idFields, $idTypes);

        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        static::removeIds($record, $idFields);
        $extras['updates'] = $record;

        $out = [];
        $errors = [];
        try {
            foreach ($ids as $index => $id) {
                try {
                    if (false === $id = static::checkForIds($id, $this->tableIdsInfo, $extras, true)) {
                        throw new BadRequestException("Required id field(s) not valid in request $index: " .
                            print_r($id, true));
                    }

                    $result = $this->addToTransaction(null, $id, $extras, $rollback, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || $rollback || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be updated.';
            }

            if ($rollback) {
                $this->rollbackTransaction();

                $msg .= " All changes rolled back.";
            }

            if ($ex instanceof RestException) {
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to update records in '$table'.\n$msg", null, null,
                $context);
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordById($table, $record, $id, $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'The request contains no valid record fields.');

        $results = $this->updateRecordsByIds($table, $record, $id, $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecords($table, $records, $extras = [])
    {
        $records = static::validateAsArray($records, null, true, 'The request contains no valid record sets.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $isSingle = (1 == count($records));
        $rollback = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::ROLLBACK, false));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));
        if ($rollback && $continue) {
            throw new BadRequestException('Rollback and continue operations can not be requested at the same time.');
        }

        $this->initTransaction($table, $idFields, $idTypes);

        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        $out = [];
        $errors = [];
        try {
            foreach ($records as $index => $record) {
                try {
                    if (false === $id = static::checkForIds($record, $this->tableIdsInfo, $extras)) {
                        throw new BadRequestException("Required id field(s) not found in record $index: " .
                            print_r($record, true));
                    }

                    $result = $this->addToTransaction($record, $id, $extras, $rollback, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || $rollback || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be patched.';
            }

            if ($rollback) {
                $this->rollbackTransaction();

                $msg .= " All changes rolled back.";
            }

            if ($ex instanceof RestException) {
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to patch records in '$table'.\n$msg", null, null,
                $context);
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecord($table, $record, $extras = [])
    {
        $records = static::validateAsArray($record, null, true, 'The request contains no valid record fields.');

        $results = $this->patchRecords($table, $records, $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table
     * @param  array $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordsByFilter($table, $record, $filter = null, $params = [], $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'There are no fields in the record.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);

        // slow, but workable for now, maybe faster than merging individuals
        $extras[ApiOptions::FIELDS] = '';
        $records = $this->retrieveRecordsByFilter($table, $filter, $params, $extras);
        unset($records['meta']);

        $fieldsInfo = $this->getFieldsInfo($table);
        $idsInfo = $this->getIdsInfo($table, $fieldsInfo, $idFields, $idTypes);
        if (empty($idsInfo)) {
            throw new InternalServerErrorException("Identifying field(s) could not be determined.");
        }

        $ids = static::recordsAsIds($records, $idsInfo);
        if (empty($ids)) {
            return [];
        }

        $extras[ApiOptions::FIELDS] = $fields;

        return $this->patchRecordsByIds($table, $record, $ids, $extras);
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordsByIds($table, $record, $ids, $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'There are no fields in the record.');
        $ids = static::validateAsArray($ids, ',', true, 'The request contains no valid identifiers.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $isSingle = (1 == count($ids));
        $rollback = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::ROLLBACK, false));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));
        if ($rollback && $continue) {
            throw new BadRequestException('Rollback and continue operations can not be requested at the same time.');
        }

        $this->initTransaction($table, $idFields, $idTypes);

        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        static::removeIds($record, $idFields);
        $extras['updates'] = $record;

        $out = [];
        $errors = [];
        try {
            foreach ($ids as $index => $id) {
                try {
                    if (false === $id = static::checkForIds($id, $this->tableIdsInfo, $extras, true)) {
                        throw new BadRequestException("Required id field(s) not valid in request $index: " .
                            print_r($id, true));
                    }

                    $result = $this->addToTransaction(null, $id, $extras, $rollback, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || $rollback || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be patched.';
            }

            if ($rollback) {
                $this->rollbackTransaction();

                $msg .= " All changes rolled back.";
            }

            if ($ex instanceof RestException) {
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to patch records in '$table'.\n$msg", null, null,
                $context);
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordById($table, $record, $id, $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'The request contains no valid record fields.');

        $results = $this->patchRecordsByIds($table, $record, $id, $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecords($table, $records, $extras = [])
    {
        $records = static::validateAsArray($records, null, true, 'The request contains no valid record sets.');

        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $fieldsInfo = $this->getFieldsInfo($table);
        $idsInfo = $this->getIdsInfo($table, $fieldsInfo, $idFields, $idTypes);
        if (empty($idsInfo)) {
            throw new InternalServerErrorException("Identifying field(s) could not be determined.");
        }

        $ids = [];
        foreach ($records as $record) {
            $ids[] = static::checkForIds($record, $idsInfo, $extras);
        }

        return $this->deleteRecordsByIds($table, $ids, $extras);
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecord($table, $record, $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'The request contains no valid record fields.');

        $results = $this->deleteRecords($table, [$record], $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByFilter($table, $filter, $params = [], $extras = [])
    {
        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);

        // slow, but workable for now, maybe faster than deleting individuals
        $extras[ApiOptions::FIELDS] = '';
        $records = $this->retrieveRecordsByFilter($table, $filter, $params, $extras);
        unset($records['meta']);

        $fieldsInfo = $this->getFieldsInfo($table);
        $idsInfo = $this->getIdsInfo($table, $fieldsInfo, $idFields, $idTypes);
        if (empty($idsInfo)) {
            throw new InternalServerErrorException("Identifying field(s) could not be determined.");
        }

        $ids = static::recordsAsIds($records, $idsInfo, $extras);
        if (empty($ids)) {
            return [];
        }

        $extras[ApiOptions::FIELDS] = $fields;

        return $this->deleteRecordsByIds($table, $ids, $extras);
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByIds($table, $ids, $extras = [])
    {
        $ids = static::validateAsArray($ids, ',', true, 'The request contains no valid identifiers.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $isSingle = (1 == count($ids));
        $rollback = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::ROLLBACK, false));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));
        if ($rollback && $continue) {
            throw new BadRequestException('Rollback and continue operations can not be requested at the same time.');
        }

        $this->initTransaction($table, $idFields, $idTypes);

        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        $out = [];
        $errors = [];
        try {
            foreach ($ids as $index => $id) {
                try {
                    if (false === $id = static::checkForIds($id, $this->tableIdsInfo, $extras, true)) {
                        throw new BadRequestException("Required id field(s) not valid in request $index: " .
                            print_r($id, true));
                    }

                    $result = $this->addToTransaction(null, $id, $extras, $rollback, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || $rollback || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be deleted.';
            }

            if ($rollback) {
                $this->rollbackTransaction();

                $msg .= " All changes rolled back.";
            }

            if ($ex instanceof RestException) {
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to delete records from '$table'.\n$msg", null, null,
                $context);
        }
    }

    /**
     * @param string $table
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordById($table, $id, $extras = [])
    {
        $results = $this->deleteRecordsByIds($table, $id, $extras);

        return array_get($results, 0, []);
    }

    /**
     * Delete all table entries but keep the table
     *
     * @param string $table
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function truncateTable($table, $extras = [])
    {
        // todo faster way?
        $records = $this->retrieveRecordsByFilter($table, null, null, $extras);

        if (!empty($records)) {
            $this->deleteRecords($table, $records, $extras);
        }

        return ['success' => true];
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function retrieveRecordsByFilter($table, $filter = null, $params = [], $extras = []);

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecords($table, $records, $extras = [])
    {
        $records = static::validateAsArray($records, null, true, 'The request contains no valid record sets.');

        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);

        $fieldsInfo = $this->getFieldsInfo($table);
        $idsInfo = $this->getIdsInfo($table, $fieldsInfo, $idFields, $idTypes);
        if (empty($idsInfo)) {
            throw new InternalServerErrorException("Identifying field(s) could not be determined.");
        }

        $ids = [];
        foreach ($records as $record) {
            $ids[] = static::checkForIds($record, $idsInfo, $extras);
        }

        return $this->retrieveRecordsByIds($table, $ids, $extras);
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecord($table, $record, $extras = [])
    {
        $record = static::validateAsArray($record, null, false, 'The request contains no valid record fields.');

        $results = $this->retrieveRecords($table, [$record], $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordsByIds($table, $ids, $extras = [])
    {
        $ids = static::validateAsArray($ids, ',', true, 'The request contains no valid identifiers.');

        $fields = array_get($extras, ApiOptions::FIELDS);
        $idFields = array_get($extras, ApiOptions::ID_FIELD);
        $idTypes = array_get($extras, ApiOptions::ID_TYPE);
        $isSingle = (1 == count($ids));
        $continue = ($isSingle) ? false : Scalar::boolval(array_get($extras, ApiOptions::CONTINUES, false));

        $this->initTransaction($table, $idFields, $idTypes);

        $extras['single'] = $isSingle;
        $extras['id_fields'] = $idFields;
        $extras['require_more'] = static::requireMoreFields($fields, $idFields);

        $out = [];
        $errors = [];
        try {
            foreach ($ids as $index => $id) {
                try {
                    if (false === $id = static::checkForIds($id, $this->tableIdsInfo, $extras, true)) {
                        throw new BadRequestException("Required id field(s) not valid in request $index: " .
                            print_r($id, true));
                    }

                    $result = $this->addToTransaction(null, $id, $extras, false, $continue, $isSingle);
                    if (isset($result)) {
                        // operation performed, take output
                        $out[$index] = $result;
                    }
                } catch (\Exception $ex) {
                    if ($isSingle || !$continue) {
                        if (0 !== $index) {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $errors[] = $index;
                            $out[$index] = $ex->getMessage();
                        }

                        throw $ex;
                    }

                    // mark error and index for batch results
                    $errors[] = $index;
                    $out[$index] = $ex->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new BadRequestException();
            }

            $result = $this->commitTransaction($extras);
            if (isset($result)) {
                $out = $result;
            }

            return $out;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();

            $context = null;
            if (!empty($errors)) {
                $wrapper = ResourcesWrapper::getWrapper();
                $context = ['error' => $errors, $wrapper => $out];
                $msg = 'Batch Error: Not all records could be retrieved.';
            }

            if ($ex instanceof RestException) {
                $temp = $ex->getContext();
                $context = (empty($temp)) ? $context : $temp;
                $ex->setContext($context);
                $ex->setMessage($msg);
                throw $ex;
            }

            throw new InternalServerErrorException("Failed to retrieve records from '$table'.\n$msg", null, null,
                $context);
        }
    }

    /**
     * @param string $table
     * @param mixed  $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordById($table, $id, $extras = [])
    {
        $results = $this->retrieveRecordsByIds($table, $id, $extras);

        return array_get($results, 0, []);
    }

    /**
     * @param string $table_name
     * @param        $id_fields
     * @param        $id_types
     * @param bool   $require_ids
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function initTransaction($table_name, &$id_fields = null, $id_types = null, $require_ids = true)
    {
        $this->transactionTable = $table_name;
        $this->tableFieldsInfo = $this->getFieldsInfo($table_name);
        $this->tableIdsInfo = $this->getIdsInfo($table_name, $this->tableFieldsInfo, $id_fields, $id_types);
        $this->batchRecords = [];
        $this->batchIds = [];
        $this->rollbackRecords = [];

        if ($require_ids && empty($this->tableIdsInfo)) {
            throw new InternalServerErrorException("Identifying field(s) could not be determined.");
        }

        return true;
    }

    /**
     * @param mixed      $record
     * @param mixed      $id
     * @param null|array $extras Additional items needed to complete the transaction
     * @param bool       $rollback
     * @param bool       $continue
     * @param bool       $single
     *
     * @throws NotImplementedException
     * @return null|array Array of output fields
     */
    protected function addToTransaction(
        $record = null,
        $id = null,
        /** @noinspection PhpUnusedParameterInspection */
        $extras = null,
        /** @noinspection PhpUnusedParameterInspection */
        $rollback = false,
        /** @noinspection PhpUnusedParameterInspection */
        $continue = false,
        /** @noinspection PhpUnusedParameterInspection */
        $single = false
    ) {
        if (!empty($record)) {
            $this->batchRecords[] = $record;
        }
        if (!empty($id)) {
            $this->batchIds[] = $id;
        }

        return null;
    }

    /**
     * @param null|array $extras Additional items needed to complete the transaction
     *
     * @throws NotFoundException
     * @throws BadRequestException
     * @return array Array of output records
     */
    abstract protected function commitTransaction($extras = null);

    /**
     * @param mixed $record
     *
     * @return bool
     */
    protected function addToRollback($record)
    {
        if (!empty($record)) {
            $this->rollbackRecords[] = $record;
        }

        return true;
    }

    /**
     * @return bool
     */
    abstract protected function rollbackTransaction();

    // Related records helpers

    /**
     * @param array            $record Record containing relationships by name if any
     * @param RelationSchema[] $relations
     *
     * @throws InternalServerErrorException
     * @return void
     */
    protected function updatePreRelations(&$record, $relations)
    {
        $record = array_change_key_case($record, CASE_LOWER);
        foreach ($relations as $name => $relationInfo) {
            if (!empty($relatedRecords = array_get($record, $name))) {
                switch ($relationInfo->type) {
                    case RelationSchema::BELONGS_TO:
                        $this->updateBelongsTo($relationInfo, $record, $relatedRecords);
                        unset($record[$name]);
                        break;
                }
            }
        }
    }

    /**
     * @param string           $table
     * @param array            $record Record containing relationships by name if any
     * @param RelationSchema[] $relations
     * @param bool             $allow_delete
     *
     * @throws InternalServerErrorException
     * @return void
     */
    protected function updatePostRelations($table, $record, $relations, $allow_delete = false)
    {
        $schema = $this->getTableSchema(null, $table);
        $record = array_change_key_case($record, CASE_LOWER);
        foreach ($relations as $name => $relationInfo) {
            if (array_key_exists($name, $record)) {
                $relatedRecords = $record[$name];
                unset($record[$name]);
                switch ($relationInfo->type) {
                    case RelationSchema::HAS_MANY:
                        $this->assignManyToOne(
                            $schema,
                            $record,
                            $relationInfo,
                            $relatedRecords,
                            $allow_delete
                        );
                        break;
                    case RelationSchema::MANY_MANY:
                        $this->assignManyToOneByJunction(
                            $schema,
                            $record,
                            $relationInfo,
                            $relatedRecords
                        );
                        break;
                }
            }
        }
    }

    /**
     * @param TableSchema      $schema
     * @param RelationSchema[] $relations
     * @param string|array     $requests
     * @param array            $data
     *
     * @throws InternalServerErrorException
     * @throws BadRequestException
     * @return void
     */
    protected function retrieveRelatedRecords(TableSchema $schema, $relations, $requests, &$data)
    {
        $relatedExtras = [ApiOptions::LIMIT => static::getMaxRecordsReturnedLimit(), ApiOptions::FIELDS => '*'];
        foreach ($relations as $key => $relation) {
            if (empty($relation)) {
                throw new BadRequestException("Empty relationship found.");
            }

            if (is_array($requests) && array_key_exists($key, $requests)) {
                $this->retrieveRelationRecords($schema, $relation, $data, $requests[$key]);
            } elseif (('*' == $requests) || $relation->alwaysFetch) {
                $this->retrieveRelationRecords($schema, $relation, $data, $relatedExtras);
            }
        }
    }

    /**
     * @param string $serviceName
     * @param string $resource
     * @param null   $params
     *
     * @return mixed|null
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function retrieveVirtualRecords($serviceName, $resource, $params = null)
    {
        if (empty($serviceName)) {
            return null;
        }

        $result = null;
        $params = (is_array($params) ? $params : []);

        $request = new Service2ServiceRequest(Verbs::GET, $params);

        //  Now set the request object and go...
        $service = ServiceManager::getService($serviceName);
        $response = $service->handleRequest($request, $resource);
        $content = $response->getContent();
        $status = $response->getStatusCode();

        if (empty($content)) {
            // No content specified.
            return null;
        }

        switch ($status) {
            case 200:
                if (isset($content)) {
                    return (isset($content['resource']) ? $content['resource'] : $content);
                }

                throw new InternalServerErrorException('Virtual query succeeded but returned invalid format.');
                break;
            default:
                if (isset($content, $content['error'])) {
                    $error = $content['error'];
                    extract($error);
                    /** @noinspection PhpUndefinedVariableInspection */
                    throw new RestException($status, $message, $code);
                }

                throw new RestException($status, 'Virtual query failed but returned invalid format.');
        }
    }

    /**
     * @param string     $serviceName
     * @param string     $resource
     * @param string     $verb
     * @param null|array $records
     * @param null|array $params
     *
     * @return mixed|null
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @internal param $path
     */
    protected function handleVirtualRecords($serviceName, $resource, $verb, $records = null, $params = null)
    {
        if (empty($serviceName)) {
            return null;
        }

        $result = null;
        $params = (is_array($params) ? $params : []);

        $request = new Service2ServiceRequest($verb, $params);
        if (!empty($records)) {
            $records = ResourcesWrapper::wrapResources($records);
            $request->setContent($records);
        }

        //  Now set the request object and go...
        $service = ServiceManager::getService($serviceName);
        $response = $service->handleRequest($request, $resource);
        $content = $response->getContent();
        $status = $response->getStatusCode();

        if (empty($content)) {
            // No content specified.
            return null;
        }

        switch ($status) {
            case 200:
            case 201:
                if (isset($content)) {
                    return (isset($content['resource']) ? $content['resource'] : $content);
                }

                throw new InternalServerErrorException('Virtual query succeeded but returned invalid format.');
                break;
            default:
                if (isset($content, $content['error'])) {
                    $error = $content['error'];
                    extract($error);
                    /** @noinspection PhpUndefinedVariableInspection */
                    throw new RestException($status, $message, $code);
                }

                throw new RestException($status, 'Virtual query failed but returned invalid format.');
        }
    }

    protected function getTableSchema($service, $table)
    {
        if (!empty($service) && ($service !== $this->getServiceName())) {
            // non-native service relation, go get it
            if (!empty($result = $this->retrieveVirtualRecords($service, '_schema/' . $table))) {
                return new TableSchema($result);
            }
        } else {
            return $this->parent->getSchema()->getResource(DbResourceTypes::TYPE_TABLE, $table);
        }

        return null;
    }

    /**
     * @param string $service
     * @param string $table
     * @param array  $fields
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function replaceWithAliases($service, &$table, array &$fields)
    {
        if (!empty($refSchema = $this->getTableSchema($service, $table))) {
            $table = $refSchema->getName(true);
            foreach ($fields as &$field) {
                if (!empty($temp = $refSchema->getColumn($field))) {
                    $field = $temp->getName(true);
                }
            }
        }
    }

    /**
     * @param TableSchema    $schema
     * @param RelationSchema $relation
     * @param array          $data
     * @param array          $extras
     *
     * @return void
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \Exception
     */
    protected function retrieveRelationRecords(TableSchema $schema, RelationSchema $relation, &$data, $extras)
    {
        $relationName = $relation->getName(true);
        $localFieldInfo = $schema->getColumn($relation->field);
        $localField = $localFieldInfo->getName(true);
        $extras = (is_array($extras) ? $extras : []);

        $fieldValues = [];
        foreach ($data as $ndx => $record) {
            switch ($relation->type) {
                case RelationSchema::BELONGS_TO:
                    $data[$ndx][$relationName] = null;
                    break;
                default:
                    $data[$ndx][$relationName] = [];
                    break;
            }
            $fieldValues[$ndx] = array_get($record, $localField);
        }

        // clean up values before query
        $values = array_unique($fieldValues);
        $values = array_filter($values, function ($v) {
            return !is_null($v);
        });
        if (empty($values)) {
            return;
        }

        switch ($relation->type) {
            case RelationSchema::BELONGS_TO:
                $refService = ($this->getServiceId() !== $relation->refServiceId) ?
                    Service::getCachedNameById($relation->refServiceId) :
                    $this->getServiceName();
                $refSchema = $this->getTableSchema($refService, $relation->refTable);
                $refTable = $refSchema->getName(true);
                if (empty($refField = $refSchema->getColumn($relation->refField))) {
                    throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->refField} not found.");
                }

                // check for access
                Session::checkServicePermission(Verbs::GET, $refService, '_table/' . $refTable);

                // Get records
                $refFieldName = $refField->getName(true);
                $extras[ApiOptions::FILTER] = "($refFieldName IN (" . implode(',', $values) . '))';
                $relatedRecords = $this->retrieveVirtualRecords($refService, '_table/' . $refTable, $extras);

                // Map the records back to data
                if (!empty($relatedRecords)) {
                    foreach ($fieldValues as $ndx => $fieldValue) {
                        if (empty($fieldValue)) {
                            continue;
                        }

                        foreach ($relatedRecords as $record) {
                            if ($fieldValue === array_get($record, $refFieldName)) {
                                $data[$ndx][$relationName] = $record;
                                continue 2; // belongs_to only supports one related per record
                            }
                        }
                    }
                }
                break;
            case RelationSchema::HAS_MANY:
                $refService = ($this->getServiceId() !== $relation->refServiceId) ?
                    Service::getCachedNameById($relation->refServiceId) :
                    $this->getServiceName();
                $refSchema = $this->getTableSchema($refService, $relation->refTable);
                $refTable = $refSchema->getName(true);
                if (empty($refField = $refSchema->getColumn($relation->refField))) {
                    throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->refField} not found.");
                }

                // check for access
                Session::checkServicePermission(Verbs::GET, $refService, '_table/' . $refTable);

                // Get records
                $refFieldName = $refField->getName(true);
                $extras[ApiOptions::FILTER] = "($refFieldName IN (" . implode(',', $values) . '))';
                $relatedRecords = $this->retrieveVirtualRecords($refService, '_table/' . $refTable, $extras);

                // Map the records back to data
                if (!empty($relatedRecords)) {
                    foreach ($fieldValues as $ndx => $fieldValue) {
                        if (empty($fieldValue)) {
                            continue;
                        }

                        foreach ($relatedRecords as $record) {
                            if ($fieldValue === array_get($record, $refFieldName)) {
                                $data[$ndx][$relationName][] = $record;
                            }
                        }
                    }
                }
                break;
            case RelationSchema::MANY_MANY:
                $junctionService = ($this->getServiceId() !== $relation->junctionServiceId) ?
                    Service::getCachedNameById($relation->junctionServiceId) :
                    $this->getServiceName();
                $junctionSchema = $this->getTableSchema($junctionService, $relation->junctionTable);
                $junctionTable = $junctionSchema->getName(true);
                $junctionField = $junctionSchema->getColumn($relation->junctionField);
                $junctionRefField = $junctionSchema->getColumn($relation->junctionRefField);
                if (empty($junctionTable) ||
                    empty($junctionField) ||
                    empty($junctionRefField)
                ) {
                    throw new InternalServerErrorException('Many to many relationship not configured properly.');
                }

                // check for access
                Session::checkServicePermission(Verbs::GET, $junctionService, '_table/' . $junctionTable);

                // Get records
                $junctionFieldName = $junctionField->getName(true);
                $junctionRefFieldName = $junctionRefField->getName(true);
                $filter = "($junctionFieldName IN (" . implode(',', $values) . '))';
                $filter .= static::padOperator(DbLogicalOperators::AND_STR);
                $filter .= "($junctionRefFieldName " . DbComparisonOperators::IS_NOT_NULL . ')';
                $temp = [
                    ApiOptions::FILTER => $filter,
                    ApiOptions::FIELDS => [$junctionFieldName, $junctionRefFieldName]
                ];
                $junctionData = $this->retrieveVirtualRecords($junctionService, '_table/' . $junctionTable, $temp);
                if (!empty($junctionData)) {
                    $relatedIds = [];
                    foreach ($junctionData as $record) {
                        if (!is_null($rightValue = array_get($record, $junctionRefFieldName))) {
                            $relatedIds[] = $rightValue;
                        }
                    }
                    if (!empty($relatedIds)) {
                        $refService = ($this->getServiceId() !== $relation->refServiceId) ?
                            Service::getCachedNameById($relation->refServiceId) :
                            $this->getServiceName();
                        $refSchema = $this->getTableSchema($refService, $relation->refTable);
                        $refTable = $refSchema->getName(true);
                        if (empty($refField = $refSchema->getColumn($relation->refField))) {
                            throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->refField} not found.");
                        }
                        $refFieldName = $refField->getName(true);

                        // check for access
                        Session::checkServicePermission(Verbs::GET, $refService, '_table/' . $refTable);

                        // Get records
                        $filter = $refFieldName . ' IN (' . implode(',', $relatedIds) . ')';
                        $extras[ApiOptions::FILTER] = $filter;
                        $relatedRecords = $this->retrieveVirtualRecords($refService, '_table/' . $refTable, $extras);

                        // Map the records back to data
                        if (!empty($relatedRecords)) {
                            foreach ($fieldValues as $ndx => $fieldValue) {
                                if (empty($fieldValue)) {
                                    continue;
                                }

                                foreach ($junctionData as $junction) {
                                    if ($fieldValue === array_get($junction, $junctionFieldName)) {
                                        $rightValue = array_get($junction, $junctionRefFieldName);
                                        foreach ($relatedRecords as $record) {
                                            if ($rightValue === array_get($record, $refFieldName)) {
                                                $data[$ndx][$relationName][] = $record;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                    }
                }
                break;
            default:
                throw new InternalServerErrorException('Invalid relationship type detected.');
                break;
        }
    }

    /**
     * @param RelationSchema $relation
     * @param array          $record
     * @param array          $parent
     *
     * @throws BadRequestException
     * @return void
     */
    protected function updateBelongsTo(RelationSchema $relation, &$record, $parent)
    {
        try {
            $refService = ($this->getServiceId() !== $relation->refServiceId) ?
                Service::getCachedNameById($relation->refServiceId) :
                $this->getServiceName();
            $refSchema = $this->getTableSchema($refService, $relation->refTable);
            $refTable = $refSchema->getName(true);
            if (empty($refField = $refSchema->getColumn($relation->refField))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->refField} not found.");
            }

            if (is_array($refSchema->primaryKey)) {
                if (1 < count($refSchema->primaryKey)) {
                    // todo How to handle multiple primary keys?
                    throw new NotImplementedException("Relating records with multiple field primary keys is not currently supported.");
                } else {
                    $pkField = $refSchema->primaryKey[0];
                }
            } else {
                $pkField = $refSchema->primaryKey;
            }
            $pkField = $refSchema->getColumn($pkField);

            $pkAutoSet = $pkField->autoIncrement;
            $pkFieldAlias = $pkField->getName(true);

            // figure out which batch each related record falls into
            $insertMany = [];
            $updateMany = [];
            $id = array_get($parent, $pkFieldAlias);
            if (empty($id)) {
                if (!$pkAutoSet) {
                    throw new BadRequestException("Related record has no primary key value for '$pkFieldAlias'.");
                }

                // create new parent record
                $insertMany[] = $parent;
            } else {
                // update or insert a parent
                // check for access
                Session::checkServicePermission(Verbs::GET, $refService, '_table/' . $refTable);

                // Get records
                $filterVal = ('string' === gettype($id)) ? "'$id'" : $id;
                $temp = [ApiOptions::FILTER => "$pkFieldAlias = $filterVal"];
                $matchIds = $this->retrieveVirtualRecords($refService, '_table/' . $refTable, $temp);

                if ($found = static::findRecordByNameValue($matchIds, $pkFieldAlias, $id)) {
                    $updateMany[] = $parent;
                } else {
                    $insertMany[] = $parent;
                }
            }

            if (!empty($insertMany)) {
                if (!empty($newIds = $this->createForeignRecords($refService, $refSchema, $insertMany))) {
                    if ($relation->refField === $pkFieldAlias) {
                        $record[$relation->field] = array_get(reset($newIds), $pkFieldAlias);
                    } else {
                        $record[$relation->field] = array_get(reset($insertMany), $relation->refField);
                    }
                }
            }

            if (!empty($updateMany)) {
                $this->updateForeignRecords($refService, $refSchema, $pkField, $updateMany);
            }
        } catch (\Exception $ex) {
            throw new BadRequestException("Failed to update belongs-to assignment.\n{$ex->getMessage()}");
        }
    }

    /**
     * @param TableSchema    $one_table
     * @param array          $one_record
     * @param RelationSchema $relation
     * @param array          $many_records
     * @param bool           $allow_delete
     *
     * @throws BadRequestException
     * @return void
     */
    protected function assignManyToOne(
        TableSchema $one_table,
        $one_record,
        RelationSchema $relation,
        $many_records = [],
        $allow_delete = false
    ) {
        // update currently only supports one id field
        if (empty($one_id = array_get($one_record, $relation->field))) {
            throw new BadRequestException("The {$one_table->getName(true)} id can not be empty.");
        }

        try {
            $refService = ($this->getServiceId() !== $relation->refServiceId) ?
                Service::getCachedNameById($relation->refServiceId) :
                $this->getServiceName();
            $refSchema = $this->getTableSchema($refService, $relation->refTable);
            $refTable = $refSchema->getName(true);
            if (empty($refField = $refSchema->getColumn($relation->refField))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->refField} not found.");
            }

            if (is_array($refSchema->primaryKey)) {
                if (1 < count($refSchema->primaryKey)) {
                    // todo How to handle multiple primary keys?
                    throw new NotImplementedException("Relating records with multiple field primary keys is not currently supported.");
                } else {
                    $pkField = $refSchema->primaryKey[0];
                }
            } else {
                $pkField = $refSchema->primaryKey;
            }
            $pkField = $refSchema->getColumn($pkField);

            $pkAutoSet = $pkField->autoIncrement;
            $pkFieldAlias = $pkField->getName(true);
            $refFieldAlias = $refField->getName(true);
            $deleteRelated = (!$refField->allowNull && $allow_delete);

            // figure out which batch each related record falls into
            $relateMany = [];
            $disownMany = [];
            $insertMany = [];
            $updateMany = [];
            $upsertMany = [];
            $deleteMany = [];
            foreach ($many_records as $item) {
                $id = array_get($item, $pkFieldAlias);
                if (empty($id)) {
                    if (!$pkAutoSet) {
                        throw new BadRequestException("Related record has no primary key value for '$pkFieldAlias'.");
                    }

                    // create new child record
                    $item[$refFieldAlias] = $one_id; // assign relationship
                    $insertMany[] = $item;
                } else {
                    if (array_key_exists($refFieldAlias, $item)) {
                        if (null == array_get($item, $refFieldAlias)) {
                            // disown this child or delete them
                            if ($deleteRelated) {
                                $deleteMany[] = $id;
                            } elseif (count($item) > 1) {
                                $item[$refFieldAlias] = null; // assign relationship
                                $updateMany[] = $item;
                            } else {
                                $disownMany[] = $id;
                            }

                            continue;
                        }
                    }

                    // update or upsert this child
                    if (count($item) > 1) {
                        $item[$refFieldAlias] = $one_id; // assign relationship
                        if ($pkAutoSet) {
                            $updateMany[] = $item;
                        } else {
                            $upsertMany[$id] = $item;
                        }
                    } else {
                        $relateMany[] = $id;
                    }
                }
            }

            // resolve any upsert situations
            if (!empty($upsertMany)) {
                // check for access
                Session::checkServicePermission(Verbs::GET, $refService, '_table/' . $refTable);

                // Get records
                $checkIds = array_keys($upsertMany);
                if (count($checkIds) > 1) {
                    $filter = $pkFieldAlias . ' IN (' . implode(',', $checkIds) . ')';
                } else {
                    $filter = $pkFieldAlias . ' = ' . $checkIds[0];
                }
                $temp = [ApiOptions::FILTER => $filter];
                $matchIds = $this->retrieveVirtualRecords($refService, '_table/' . $refTable, $temp);

                foreach ($upsertMany as $uId => $record) {
                    if ($found = static::findRecordByNameValue($matchIds, $pkFieldAlias, $uId)) {
                        $updateMany[] = $record;
                    } else {
                        $insertMany[] = $record;
                    }
                }
            }

            // Now handle the batches
            if (!empty($insertMany)) {
                // create new children
                $this->createForeignRecords($refService, $refSchema, $insertMany);
            }

            if (!empty($deleteMany)) {
                // destroy linked children that can't stand alone - sounds sinister
                $this->deleteForeignRecords($refService, $refSchema, $pkField, $deleteMany);
            }

            if (!empty($updateMany)) {
                $this->updateForeignRecords($refService, $refSchema, $pkField, $updateMany);
            }

            if (!empty($relateMany)) {
                // adopt/relate/link unlinked children
                $updates = [$refFieldAlias => $one_id];
                $this->updateForeignRecordsByIds($refService, $refSchema, $pkField, $relateMany, $updates);
            }

            if (!empty($disownMany)) {
                // disown/un-relate/unlink linked children
                $updates = [$refFieldAlias => null];
                $this->updateForeignRecordsByIds($refService, $refSchema, $pkField, $disownMany, $updates);
            }
        } catch (\Exception $ex) {
            throw new BadRequestException("Failed to update many to one assignment.\n{$ex->getMessage()}");
        }
    }

    protected function createForeignRecords($service, TableSchema $schema, $records)
    {
        // do we have permission to do so?
        Session::checkServicePermission(Verbs::POST, $service, '_table/' . $schema->getName(true));
//        if (!empty($service) && ($service !== $this->getServiceName())) {
            $newIds = $this->handleVirtualRecords($service, '_table/' . $schema->getName(true), Verbs::POST, $records);
//        } else {
//            $tableName = $schema->getName();
//            $builder = $this->dbConn->table($tableName);
//            $fields = $schema->getColumns(true);
//            $ssFilters = Session::getServiceFilters(Verbs::POST, $service, $schema->getName(true));
//            $newIds = [];
//            foreach ($records as $record) {
//                $parsed = $this->parseRecord($record, $fields, $ssFilters);
//                if (empty($parsed)) {
//                    throw new BadRequestException('No valid fields were found in record.');
//                }
//
//                $newIds[] = (int)$builder->insertGetId($parsed, $schema->primaryKey);
//            }
//        }

        return $newIds;
    }

    protected function updateForeignRecords(
        $service,
        TableSchema $schema,
        ColumnSchema $linkerField,
        $records
    ) {
        // do we have permission to do so?
        Session::checkServicePermission(Verbs::PUT, $service, '_table/' . $schema->getName(true));
//        if (!empty($service) && ($service !== $this->getServiceName())) {
            $this->handleVirtualRecords($service, '_table/' . $schema->getName(true), Verbs::PATCH, $records);
//        } else {
//            $fields = $schema->getColumns(true);
//            $ssFilters = Session::getServiceFilters(Verbs::PUT, $service, $schema->getName(true));
//            // update existing and adopt new children
//            foreach ($records as $record) {
//                $pk = array_get($record, $linkerField->getName(true));
//                $parsed = $this->parseRecord($record, $fields, $ssFilters, true);
//                if (empty($parsed)) {
//                    throw new BadRequestException('No valid fields were found for foreign link updates.');
//                }
//
//                $builder = $this->dbConn->table($schema->getName());
//                $builder->where($linkerField->name, $pk);
//                $serverFilter = $this->buildQueryStringFromData($ssFilters);
//                if (!empty($serverFilter)) {
//                    Session::replaceLookups($serverFilter);
//                    $params = [];
//                    $filterString = $this->parseFilterString($serverFilter, $params, $this->tableFieldsInfo);
//                    $builder->whereRaw($filterString, $params);
//                }
//
//                $rows = $builder->update($parsed);
//                if (0 >= $rows) {
////            throw new NotFoundException( 'No foreign linked records were found using the given identifiers.' );
//                }
//            }
//        }
    }

    protected function updateForeignRecordsByIds(
        $service,
        TableSchema $schema,
        ColumnSchema $linkerField,
        $linkerIds,
        $record
    ) {
        // do we have permission to do so?
        Session::checkServicePermission(Verbs::PUT, $service, '_table/' . $schema->getName(true));
//        if (!empty($service) && ($service !== $this->getServiceName())) {
            $temp = [ApiOptions::IDS => $linkerIds, ApiOptions::ID_FIELD => $linkerField->getName(true)];
            $this->handleVirtualRecords($service, '_table/' . $schema->getName(true), Verbs::PATCH, $record, $temp);
//        } else {
//            $fields = $schema->getColumns(true);
//            $ssFilters = Session::getServiceFilters(Verbs::PUT, $service, $schema->getName(true));
//            $parsed = $this->parseRecord($record, $fields, $ssFilters, true);
//            if (empty($parsed)) {
//                throw new BadRequestException('No valid fields were found for foreign link updates.');
//            }
//            $builder = $this->dbConn->table($schema->getName());
//            $builder->whereIn($linkerField->name, $linkerIds);
//            $serverFilter = $this->buildQueryStringFromData($ssFilters);
//            if (!empty($serverFilter)) {
//                Session::replaceLookups($serverFilter);
//                $params = [];
//                $filterString = $this->parseFilterString($serverFilter, $params, $this->tableFieldsInfo);
//                $builder->whereRaw($filterString, $params);
//            }
//
//            $rows = $builder->update($parsed);
//            if (0 >= $rows) {
////            throw new NotFoundException( 'No foreign linked records were found using the given identifiers.' );
//            }
//        }
    }

    protected function deleteForeignRecords(
        $service,
        TableSchema $schema,
        ColumnSchema $linkerField,
        $linkerIds,
        $addCondition = null
    ) {
        // do we have permission to do so?
        Session::checkServicePermission(Verbs::DELETE, $service, '_table/' . $schema->getName(true));
//        if (!empty($service) && ($service !== $this->getServiceName())) {
            if (!empty($addCondition) && is_array($addCondition)) {
                $filter = '(' . $linkerField->getName(true) . ' IN (' . implode(',', $$linkerIds) . '))';
                foreach ($addCondition as $key => $value) {
                    $column = $schema->getColumn($key);
                    $filter .= 'AND (' . $column->getName(true) . ' = ' . $value . ')';
                }
                $temp = [ApiOptions::FILTER => $filter];
            } else {
                $temp = [ApiOptions::IDS => $linkerIds, ApiOptions::ID_FIELD => $linkerField->getName(true)];
            }

            $this->handleVirtualRecords($service, '_table/' . $schema->getName(true), Verbs::DELETE, null, $temp);
//        } else {
//            $builder = $this->dbConn->table($schema->getName());
//            $builder->whereIn($linkerField->name, $linkerIds);
//
//            $ssFilters = Session::getServiceFilters(Verbs::DELETE, $service, $schema->getName(true));
//            $serverFilter = $this->buildQueryStringFromData($ssFilters);
//            if (!empty($serverFilter)) {
//                Session::replaceLookups($serverFilter);
//                $params = [];
//                $filterString = $this->parseFilterString($serverFilter, $params, $this->tableFieldsInfo);
//                $builder->whereRaw($filterString, $params);
//            }
//
//            if (!empty($addCondition) && is_array($addCondition)) {
//                foreach ($addCondition as $key => $value) {
//                    $column = $schema->getColumn($key);
//                    $builder->where($column->name, $value);
//                }
//            }
//
//            $rows = $builder->delete();
//            if (0 >= $rows) {
////            throw new NotFoundException( 'No foreign linked records were found using the given identifiers.' );
//            }
//        }
    }

    /**
     * @param TableSchema    $one_table
     * @param array          $one_record
     * @param RelationSchema $relation
     * @param array          $many_records
     *
     * @throws InternalServerErrorException
     * @throws BadRequestException
     * @return void
     */
    protected function assignManyToOneByJunction(
        TableSchema $one_table,
        $one_record,
        RelationSchema $relation,
        $many_records = []
    ) {
        if (empty($one_id = array_get($one_record, $relation->field))) {
            throw new BadRequestException("The {$one_table->getName(true)} id can not be empty.");
        }

        try {
            if (is_array($one_table->primaryKey)) {
                if (1 !== count($one_table->primaryKey)) {
                    // todo How to handle multiple primary keys?
                    throw new NotImplementedException("Relating records with multiple field primary keys is not currently supported.");
                } else {
                    $onePkFieldName = $one_table->primaryKey[0];
                }
            } else {
                $onePkFieldName = $one_table->primaryKey;
            }
            if (empty($onePkField = $one_table->getColumn($onePkFieldName))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '$onePkFieldName' not found.");
            }

            $refService = ($this->getServiceId() !== $relation->refServiceId) ?
                Service::getCachedNameById($relation->refServiceId) :
                $this->getServiceName();
            $refSchema = $this->getTableSchema($refService, $relation->refTable);
            $refTable = $refSchema->getName(true);
            if (empty($refField = $refSchema->getColumn($relation->refField))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->refField} not found.");
            }
            if (is_array($refSchema->primaryKey)) {
                if (1 !== count($refSchema->primaryKey)) {
                    // todo How to handle multiple primary keys?
                    throw new NotImplementedException("Relating records with multiple field primary keys is not currently supported.");
                } else {
                    $refPkFieldName = $refSchema->primaryKey[0];
                }
            } else {
                $refPkFieldName = $refSchema->primaryKey;
            }
            if (empty($refPkField = $refSchema->getColumn($refPkFieldName))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '$refPkFieldName' not found.");
            }

            $junctionService = ($this->getServiceId() !== $relation->junctionServiceId) ?
                Service::getCachedNameById($relation->junctionServiceId) :
                $this->getServiceName();
            $junctionSchema = $this->getTableSchema($junctionService, $relation->junctionTable);
            $junctionTable = $junctionSchema->getName(true);
            if (empty($junctionField = $junctionSchema->getColumn($relation->junctionField))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->junctionField} not found.");
            }
            if (empty($junctionRefField = $junctionSchema->getColumn($relation->junctionRefField))) {
                throw new InternalServerErrorException("Incorrect relationship configuration detected. Field '{$relation->junctionRefField} not found.");
            }

            // check for access
            Session::checkServicePermission(Verbs::GET, $junctionService, '_table/' . $junctionTable);

            // Get records
            $filter =
                $junctionField->getName(true) . " = $one_id AND (" . $junctionRefField->getName(true) . " IS NOT NULL)";
            $temp = [ApiOptions::FILTER => $filter, ApiOptions::FIELDS => $junctionRefField->getName(true)];

            $maps = $this->retrieveVirtualRecords($junctionService, '_table/' . $junctionTable, $temp);

            $createMap = []; // map records to create
            $deleteMap = []; // ids of 'many' records to delete from maps
            $insertMany = [];
            $updateMany = [];
            $upsertMany = [];

            $pkAutoSet = $refPkField->autoIncrement;
            $refPkFieldAlias = $refPkField->getName(true);
            foreach ($many_records as $item) {
                $id = array_get($item, $refPkFieldAlias);
                if (empty($id)) {
                    if (!$pkAutoSet) {
                        throw new BadRequestException("Related record has no primary key value for '$refPkFieldAlias'.");
                    }

                    // create new child record
                    $insertMany[] = $item;
                } else {
                    // pk fields exists, must be dealing with existing 'many' record
                    $oneLookup = $one_table->getName(true) . '.' . $onePkField->getName(true);
                    if (array_key_exists($oneLookup, $item)) {
                        if (null == array_get($item, $oneLookup)) {
                            // delete this relationship
                            $deleteMap[] = $id;
                            continue;
                        }
                    }

                    // update the 'many' record if more than the above fields
                    if (count($item) > 1) {
                        if ($pkAutoSet) {
                            $updateMany[] = $item;
                        } else {
                            $upsertMany[$id] = $item;
                        }
                    }

                    // if relationship doesn't exist, create it
                    foreach ($maps as $map) {
                        if (array_get($map, $junctionRefField->getName(true)) == $id) {
                            continue 2; // got what we need from this one
                        }
                    }

                    $createMap[] = [$junctionRefField->getName(true) => $id, $junctionField->getName(true) => $one_id];
                }
            }

            // resolve any upsert situations
            if (!empty($upsertMany)) {
                // check for access
                Session::checkServicePermission(Verbs::GET, $refService, '_table/' . $refTable);

                // Get records
                $checkIds = array_keys($upsertMany);
                if (count($checkIds) > 1) {
                    $filter = $refPkFieldAlias . ' IN (' . implode(',', $checkIds) . ')';
                } else {
                    $filter = $refPkFieldAlias . ' = ' . $checkIds[0];
                }
                $temp = [ApiOptions::FILTER => $filter];
                $matchIds = $this->retrieveVirtualRecords($refService, '_table/' . $refTable, $temp);

                foreach ($upsertMany as $uId => $record) {
                    if ($found = static::findRecordByNameValue($matchIds, $refPkFieldAlias, $uId)) {
                        $updateMany[] = $record;
                    } else {
                        $insertMany[] = $record;
                    }
                }
            }

            if (!empty($insertMany)) {
                $refIds = $this->createForeignRecords($refService, $refSchema, $insertMany);
                // create new many records
                foreach ($refIds as $refId) {
                    if (!empty($refId)) {
                        $createMap[] =
                            [$junctionRefField->getName(true) => $refId, $junctionField->getName(true) => $one_id];
                    }
                }
            }

            if (!empty($updateMany)) {
                // update existing many records
                $this->updateForeignRecords($refService, $refSchema, $refPkField, $updateMany);
            }

            if (!empty($createMap)) {
                $this->createForeignRecords($junctionService, $junctionSchema, $createMap);
            }

            if (!empty($deleteMap)) {
                $addCondition = [$junctionField->getName() => $one_id];
                $this->deleteForeignRecords($junctionService, $junctionSchema, $junctionRefField, $deleteMap,
                    $addCondition);
            }
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to update many to one map assignment.\n{$ex->getMessage()}");
        }
    }

    // Helper function for record usage

    /**
     * @param $table_name
     *
     * @return ColumnSchema[]
     * @throws \Exception
     */
    protected function getFieldsInfo($table_name)
    {
        $table = $this->schema->getResource(DbResourceTypes::TYPE_TABLE, $table_name);
        if (!$table) {
            throw new NotFoundException("Table '$table_name' does not exist in the database.");
        }

        return $table->getColumns(true);
    }

    /**
     * @param $table_name
     *
     * @return RelationSchema[]
     * @throws \Exception
     */
    protected function describeTableRelated($table_name)
    {
        $table = $this->schema->getResource(DbResourceTypes::TYPE_TABLE, $table_name);
        if (!$table) {
            throw new NotFoundException("Table '$table_name' does not exist in the database.");
        }

        return $table->getRelations(true);
    }

    /**
     * @param      $table
     * @param null $fields_info
     * @param null $requested_fields
     * @param null $requested_types
     *
     * @return mixed
     */
    abstract protected function getIdsInfo(
        $table,
        $fields_info = null,
        &$requested_fields = null,
        $requested_types = null
    );

    /**
     * @param ColumnSchema[] $id_info
     *
     * @return array
     */
    protected function getIdFieldsFromInfo($id_info)
    {
        $fields = [];
        foreach ($id_info as $info) {
            $fields[] = $info->name;
        }

        return $fields;
    }

    /**
     * @param array          $record
     * @param ColumnSchema[] $ids_info
     * @param null           $extras
     * @param bool           $on_create
     * @param bool           $remove
     *
     * @return array|bool|int|mixed|null|string
     */
    protected static function checkForIds(&$record, $ids_info, $extras = null, $on_create = false, $remove = false)
    {
        $id = null;
        if (!empty($ids_info)) {
            if (1 == count($ids_info)) {
                $info = $ids_info[0];
                $name = $info->getName(true);
                if (is_array($record)) {
                    $value = array_get($record, $name);
                    if ($remove) {
                        unset($record[$name]);
                    }
                } else {
                    $value = $record;
                }
                if (!empty($value)) {
                    if (!is_array($value)) {
                        switch ($info->type) {
                            case 'int':
                                $value = intval($value);
                                break;
                            case 'string':
                                $value = strval($value);
                                break;
                        }
                    }
                    $id = $value;
                } else {
                    // could be passed in as a parameter affecting all records
                    $param = array_get($extras, $name);
                    if ($on_create && $info->getRequired() && empty($param)) {
                        return false;
                    }
                }
            } else {
                $id = [];
                foreach ($ids_info as $info) {
                    $name = $info->getName(true);
                    if (is_array($record)) {
                        $value = array_get($record, $name);
                        if ($remove) {
                            unset($record[$name]);
                        }
                    } else {
                        $value = $record;
                    }
                    if (!empty($value)) {
                        if (!is_array($value)) {
                            switch ($info->type) {
                                case 'int':
                                    $value = intval($value);
                                    break;
                                case 'string':
                                    $value = strval($value);
                                    break;
                            }
                        }
                        $id[$name] = $value;
                    } else {
                        // could be passed in as a parameter affecting all records
                        $param = array_get($extras, $name);
                        if ($on_create && $info->getRequired() && empty($param)) {
                            return false;
                        }
                    }
                }
            }
        }

        if (!empty($id)) {
            return $id;
        } elseif ($on_create) {
            return [];
        }

        return false;
    }

    protected function getCurrentTimestamp()
    {
        return time();
    }

    protected function parseValueForSet(
        $value,
        /** @noinspection PhpUnusedParameterInspection */
        $field_info
    ) {
        return $value;
    }

    protected function restrictFieldsToDefined()
    {
        return false;
    }

    /**
     * @param array          $record
     * @param ColumnSchema[] $fields_info
     * @param array          $filter_info
     * @param bool           $for_update
     * @param array          $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord($record, $fields_info, $filter_info = null, $for_update = false, $old_record = null)
    {
        $record = $this->interpretRecordValues($record);

        $parsed = ($this->restrictFieldsToDefined() ? [] : $record);
        if (!empty($fields_info)) {
            $record = array_change_key_case($record, CASE_LOWER);

            foreach ($fields_info as $fieldInfo) {
                // add or override for specific fields
                switch ($fieldInfo->type) {
                    case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
                        if (!$for_update) {
                            $parsed[$fieldInfo->name] = $this->getCurrentTimestamp();
                        }
                        break;
                    case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                        $parsed[$fieldInfo->name] = $this->getCurrentTimestamp();
                        break;
                    case DbSimpleTypes::TYPE_USER_ID_ON_CREATE:
                        if (!$for_update) {
                            $userId = Session::getCurrentUserId();
                            if (isset($userId)) {
                                $parsed[$fieldInfo->name] = $userId;
                            }
                        }
                        break;
                    case DbSimpleTypes::TYPE_USER_ID_ON_UPDATE:
                        $userId = Session::getCurrentUserId();
                        if (isset($userId)) {
                            $parsed[$fieldInfo->name] = $userId;
                        }
                        break;
                    default:
                        $name = strtolower($fieldInfo->getName(true));
                        // need to check for virtual or api_read_only validation here.
                        if ((DbSimpleTypes::TYPE_VIRTUAL === $fieldInfo->type) ||
                            isset($fieldInfo->validation, $fieldInfo->validation['api_read_only'])
                        ) {
                            unset($record[$name]);
                            continue;
                        }
                        // need to check for id in record and remove it, as some DBs complain.
                        if ($for_update && (DbSimpleTypes::TYPE_ID === $fieldInfo->type)) {
                            unset($record[$name]);
                            continue;
                        }
                        if (array_key_exists($name, $record)) {
                            $fieldVal = array_get($record, $name);
                            // due to conversion from XML to array, null or empty xml elements have the array value of an empty array
                            if (is_array($fieldVal) && empty($fieldVal)) {
                                $fieldVal = null;
                            }

                            if (is_null($fieldVal) && !$fieldInfo->allowNull) {
                                throw new BadRequestException("Field '$name' can not be NULL.");
                            }

                            /** validations **/
                            if (!static::validateFieldValue(
                                $fieldInfo->getName(true),
                                $fieldVal,
                                $fieldInfo->validation,
                                $for_update,
                                $fieldInfo
                            )
                            ) {
                                // if invalid and exception not thrown, drop it
                                unset($record[$name]);
                                continue;
                            }

                            try {
                                $fieldVal = $this->parseValueForSet($fieldVal, $fieldInfo);
                            } catch (ForbiddenException $ex) {
                                unset($record[$name]);
                                continue;
                            }

                            $parsed[$fieldInfo->name] = $fieldVal;
                            unset($record[$name]);
                        } else {
                            // if field is required, kick back error
                            if ($fieldInfo->getRequired() && !$for_update) {
                                throw new BadRequestException("Required field '$name' can not be NULL.");
                            }
                            break;
                        }
                        break;
                }
            }
        }

        if (!empty($filter_info)) {
            $this->validateRecord($parsed, $filter_info, $for_update, $old_record);
        }

        return $parsed;
    }

    /**
     * @param array $record
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @throws \Exception
     */
    protected function validateRecord($record, $filter_info, $for_update = false, $old_record = null)
    {
        $record = array_change_key_case($record, CASE_LOWER);
        $filters = array_get($filter_info, 'filters');

        if (empty($filters) || empty($record)) {
            return;
        }

        $combiner = array_get($filter_info, 'filter_op', 'and');
        foreach ($filters as $filter) {
            $filterField = strtolower(array_get($filter, 'name'));
            $operator = array_get($filter, 'operator');
            $filterValue = array_get($filter, 'value');
            $filterValue = static::interpretFilterValue($filterValue);
            $foundInRecord = (is_array($record)) ? array_key_exists($filterField, $record) : false;
            $recordValue = array_get($record, $filterField);

            $old_record = (array)$old_record;
            $foundInOld = array_key_exists($filterField, $old_record);
            $oldValue = array_get($old_record, $filterField);
            $compareFound = ($foundInRecord || ($for_update && $foundInOld));
            $compareValue = $foundInRecord ? $recordValue : ($for_update ? $oldValue : null);

            $reason = null;
            if ($for_update && !$compareFound) {
                // not being set, filter on update will check old record
                continue;
            }

            if (!static::compareByOperator($operator, $compareFound, $compareValue, $filterValue)) {
                $reason = "Denied access to some of the requested fields.";
            }

            switch (strtolower($combiner)) {
                case 'and':
                    if (!empty($reason)) {
                        // any reason is a good reason to bail
                        throw new ForbiddenException($reason);
                    }
                    break;
                case 'or':
                    if (empty($reason)) {
                        // at least one was successful
                        return;
                    }
                    break;
                default:
                    throw new InternalServerErrorException('Invalid server configuration detected.');
            }
        }
    }

    /**
     * @param $operator
     * @param $left_found
     * @param $left
     * @param $right
     *
     * @return bool
     * @throws InternalServerErrorException
     */
    public static function compareByOperator($operator, $left_found, $left, $right)
    {
        switch ($operator) {
            case DbComparisonOperators::EQ:
                return ($left == $right);
            case DbComparisonOperators::NE:
                return ($left != $right);
            case DbComparisonOperators::GT:
                return ($left > $right);
            case DbComparisonOperators::LT:
                return ($left < $right);
            case DbComparisonOperators::GTE:
                return ($left >= $right);
            case DbComparisonOperators::LTE:
                return ($left <= $right);
            case DbComparisonOperators::STARTS_WITH:
                return static::startsWith($left, $right);
            case DbComparisonOperators::ENDS_WITH:
                return static::endsWith($left, $right);
            case DbComparisonOperators::CONTAINS:
                return (false !== strpos($left, $right));
            case DbComparisonOperators::IN:
                return ArrayUtils::isInList($right, $left);
            case DbComparisonOperators::NOT_IN:
                return !ArrayUtils::isInList($right, $left);
            case DbComparisonOperators::IS_NULL:
                return is_null($left);
            case DbComparisonOperators::IS_NOT_NULL:
                return !is_null($left);
            case DbComparisonOperators::DOES_EXIST:
                return ($left_found);
            case DbComparisonOperators::DOES_NOT_EXIST:
                return (!$left_found);
            default:
                throw new InternalServerErrorException('Invalid server-side filter configuration detected.');
        }
    }

    /**
     * @param string            $name
     * @param mixed             $value
     * @param array             $validations
     * @param bool              $for_update
     * @param ColumnSchema|null $field_info
     *
     * @return bool
     * @throws InternalServerErrorException
     * @throws BadRequestException
     */
    protected static function validateFieldValue($name, $value, $validations, $for_update = false, $field_info = null)
    {
        if (is_array($validations)) {
            foreach ($validations as $key => $config) {
                $config = (array)$config;
                $onFail = array_get($config, 'on_fail');
                $throw = true;
                $msg = null;
                if (!empty($onFail)) {
                    if (0 == strcasecmp($onFail, 'ignore_field')) {
                        $throw = false;
                    } else {
                        $msg = $onFail;
                    }
                }

                switch ($key) {
                    case 'api_read_only':
                        if ($throw) {
                            if (empty($msg)) {
                                $msg = "Field '$name' is read only.";
                            }
                            throw new BadRequestException($msg);
                        }

                        return false;
                        break;
                    case 'create_only':
                        if ($for_update) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' can only be set during record creation.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'not_null':
                        if (is_null($value)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value can not be null.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'not_empty':
                        if (!is_null($value) && empty($value)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value can not be empty.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'not_zero':
                        if (!is_null($value) && empty($value)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value can not be empty.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value must be a valid email address.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'url':
                        $sections = (array)array_get($config, 'sections');
                        $flags = 0;
                        foreach ($sections as $format) {
                            switch (strtolower($format)) {
                                case 'path':
                                    $flags &= FILTER_FLAG_PATH_REQUIRED;
                                    break;
                                case 'query':
                                    $flags &= FILTER_FLAG_QUERY_REQUIRED;
                                    break;
                            }
                        }
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL, $flags)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value must be a valid URL.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'int':
                        $min = array_get($config, 'range.min');
                        $max = array_get($config, 'range.max');
                        $formats = (array)array_get($config, 'formats');

                        $options = [];
                        if (is_int($min)) {
                            $options['min_range'] = $min;
                        }
                        if (is_int($max)) {
                            $options['max_range'] = $max;
                        }
                        $flags = 0;
                        foreach ($formats as $format) {
                            switch (strtolower($format)) {
                                case 'hex':
                                    $flags &= FILTER_FLAG_ALLOW_HEX;
                                    break;
                                case 'octal':
                                    $flags &= FILTER_FLAG_ALLOW_OCTAL;
                                    break;
                            }
                        }
                        $options = ['options' => $options, 'flags' => $flags];
                        if (!is_null($value) && false === filter_var($value, FILTER_VALIDATE_INT, $options)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value is not in the valid range.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'float':
                        $decimal = array_get($config, 'decimal', '.');
                        $options['decimal'] = $decimal;
                        $options = ['options' => $options];
                        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_FLOAT, $options)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value is not an acceptable float value.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'boolean':
                        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value is not an acceptable boolean value.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'match':
                        $regex = array_get($config, 'regexp');
                        if (empty($regex)) {
                            throw new InternalServerErrorException("Invalid validation configuration: Field '$name' has no 'regexp'.");
                        }

                        $regex = base64_decode($regex);
                        $options = ['regexp' => $regex];
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_REGEXP, $options)) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value is invalid.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'picklist':
                        if (is_null($field_info) || empty($values = $field_info->picklist)) {
                            throw new InternalServerErrorException("Invalid validation configuration: Field '$name' has no 'picklist' options in schema settings.");
                        }

                        if (!empty($value) && (false === array_search($value, $values))) {
                            if ($throw) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value is invalid.";
                                }
                                throw new BadRequestException($msg);
                            }

                            return false;
                        }
                        break;
                    case 'multi_picklist':
                        if (is_null($field_info) || empty($values = $field_info->picklist)) {
                            throw new InternalServerErrorException("Invalid validation configuration: Field '$name' has no 'picklist' options in schema settings.");
                        }

                        if (!empty($value)) {
                            $delimiter = array_get($config, 'delimiter', ',');
                            $min = array_get($config, 'min', 1);
                            $max = array_get($config, 'max');
                            $value = static::validateAsArray($value, $delimiter, true);
                            $count = count($value);
                            if ($count < $min) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value does not contain enough selections.";
                                }
                                throw new BadRequestException($msg);
                            }
                            if (!empty($max) && ($count > $max)) {
                                if (empty($msg)) {
                                    $msg = "Field '$name' value contains too many selections.";
                                }
                                throw new BadRequestException($msg);
                            }
                            foreach ($value as $item) {
                                if (false === array_search($item, $values)) {
                                    if ($throw) {
                                        if (empty($msg)) {
                                            $msg = "Field '$name' value is invalid.";
                                        }
                                        throw new BadRequestException($msg);
                                    }

                                    return false;
                                }
                            }
                        }
                        break;
                }
            }
        }

        return true;
    }

    /**
     * @return int
     */
    protected static function getMaxRecordsReturnedLimit()
    {
        // some classes define their own default
        $default = defined('static::MAX_RECORDS_RETURNED') ? static::MAX_RECORDS_RETURNED : 1000;

        return intval(Config::get('df.db.max_records_returned', $default));
    }

    protected static function findRecordByNameValue($data, $field, $value)
    {
        foreach ($data as $record) {
            if (array_get($record, $field) === $value) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param array        $record
     * @param string|array $include  List of keys to include in the output record
     * @param string|array $id_field Single or list of identifier fields
     *
     * @return array
     */
    protected static function cleanRecord($record = [], $include = '*', $id_field = null)
    {
        if ('*' !== $include) {
            if (!empty($id_field) && !is_array($id_field)) {
                $id_field = array_map('trim', explode(',', trim($id_field, ',')));
            }
            $id_field = (array)$id_field;

            if (!empty($include) && !is_array($include)) {
                $include = array_map('trim', explode(',', trim($include, ',')));
            }
            $include = (array)$include;

            // make sure we always include identifier fields
            foreach ($id_field as $id) {
                if (false === array_search($id, $include)) {
                    $include[] = $id;
                }
            }

            // glean desired fields from record
            $out = [];
            foreach ($include as $key) {
                $out[$key] = array_get($record, $key);
            }

            return $out;
        }

        return $record;
    }

    /**
     * @param array $records
     * @param mixed $include
     * @param mixed $id_field
     *
     * @return array
     */
    protected static function cleanRecords($records, $include = '*', $id_field = null)
    {
        $out = [];
        foreach ($records as $record) {
            $out[] = static::cleanRecord($record, $include, $id_field);
        }

        return $out;
    }

    /**
     * @param array $records
     * @param       $ids_info
     * @param null  $extras
     * @param bool  $on_create
     * @param bool  $remove
     *
     * @internal param string $id_field
     * @internal param bool $include_field
     *
     * @return array
     */
    protected static function recordsAsIds($records, $ids_info, $extras = null, $on_create = false, $remove = false)
    {
        $out = [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $out[] = static::checkForIds($record, $ids_info, $extras, $on_create, $remove);
            }
        }

        return $out;
    }

    /**
     * @param array  $record
     * @param string $id_field
     * @param bool   $include_field
     * @param bool   $remove
     *
     * @throws BadRequestException
     * @return array
     */
    protected static function recordAsId(&$record, $id_field = null, $include_field = false, $remove = false)
    {
        if (empty($id_field)) {
            return [];
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        if (count($id_field) > 1) {
            $ids = [];
            foreach ($id_field as $field) {
                $id = array_get($record, $field);
                if ($remove) {
                    unset($record[$field]);
                }
                if (empty($id)) {
                    throw new BadRequestException("Identifying field '$field' can not be empty for record.");
                }
                $ids[$field] = $id;
            }

            return $ids;
        } else {
            $field = $id_field[0];
            $id = array_get($record, $field);
            if ($remove) {
                unset($record[$field]);
            }
            if (empty($id)) {
                throw new BadRequestException("Identifying field '$field' can not be empty for record.");
            }

            return ($include_field) ? [$field => $id] : $id;
        }
    }

    /**
     * @param        $ids
     * @param string $id_field
     * @param bool   $field_included
     *
     * @return array
     */
    protected static function idsAsRecords($ids, $id_field, $field_included = false)
    {
        if (empty($id_field)) {
            return [];
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        $out = [];
        foreach ($ids as $id) {
            $ids = [];
            if ((count($id_field) > 1) && (count($id) > 1)) {
                foreach ($id_field as $index => $field) {
                    $search = ($field_included) ? $field : $index;
                    $ids[$field] = array_get($id, $search);
                }
            } else {
                $field = $id_field[0];
                $ids[$field] = $id;
            }

            $out[] = $ids;
        }

        return $out;
    }

    /**
     * @param array $record
     * @param array $id_field
     */
    protected static function removeIds(&$record, $id_field)
    {
        if (!empty($id_field)) {

            if (!is_array($id_field)) {
                $id_field = array_map('trim', explode(',', trim($id_field, ',')));
            }

            foreach ($id_field as $name) {
                unset($record[$name]);
            }
        }
    }

    /**
     * @param      $record
     * @param null $id_field
     *
     * @return bool
     */
    protected static function containsIdFields($record, $id_field = null)
    {
        if (empty($id_field)) {
            return false;
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        foreach ($id_field as $field) {
            $temp = array_get($record, $field);
            if (empty($temp)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $fields
     * @param string $id_field
     *
     * @return bool
     */
    protected static function requireMoreFields($fields, $id_field = null)
    {
        if (('*' == $fields) || empty($id_field)) {
            return true;
        }

        if (false === $fields = static::validateAsArray($fields, ',')) {
            return false;
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        foreach ($id_field as $key => $name) {
            if (false !== array_search($name, $fields)) {
                unset($fields[$key]);
            }
        }

        return !empty($fields);
    }

    /**
     * @param        $first_array
     * @param        $second_array
     * @param string $id_field
     *
     * @return mixed
     */
    protected static function recordArrayMerge($first_array, $second_array, $id_field = null)
    {
        if (empty($id_field)) {
            return [];
        }

        foreach ($first_array as $key => $first) {
            $firstId = array_get($first, $id_field);
            foreach ($second_array as $second) {
                $secondId = array_get($second, $id_field);
                if ($firstId == $secondId) {
                    $first_array[$key] = array_merge($first, $second);
                }
            }
        }

        return $first_array;
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
        Session::replaceLookups($value);

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
            Session::replaceLookups($value);
            $record[$field] = $value;
        }

        return $record;
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return (substr($haystack, -strlen($needle)) === $needle);
    }

    public static function getApiDocModels()
    {
        $wrapper = ResourcesWrapper::getWrapper();
        $commonProperties = [
            'id' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Sample identifier of this record.',
            ],
        ];

        return [
            'Tables'          => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of tables and their properties.',
                        'items'       => [
                            '$ref' => '#/definitions/Table',
                        ],
                    ],
                ],
            ],
            'Table'           => [
                'type'       => 'object',
                'properties' => [
                    'name' => [
                        'type'        => 'string',
                        'description' => 'Name of the table.',
                    ],
                ],
            ],
            'RecordRequest'   => [
                'type'       => 'object',
                'properties' =>
                    $commonProperties
            ],
            'RecordsRequest'  => [
                'type'       => 'object',
                'properties' => [
                    $wrapper           => [
                        'type'        => 'array',
                        'description' => 'Array of records.',
                        'items'       => [
                            '$ref' => '#/definitions/RecordRequest',
                        ],
                    ],
                    ApiOptions::IDS    => [
                        'type'        => 'array',
                        'description' => 'Array of record identifiers.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                    ApiOptions::FILTER => [
                        'type'        => 'string',
                        'description' => 'SQL or native filter to determine records where modifications will be applied.',
                    ],
                    ApiOptions::PARAMS => [
                        'type'        => 'array',
                        'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'RecordResponse'  => [
                'type'       => 'object',
                'properties' => $commonProperties
            ],
            'RecordsResponse' => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system user records.',
                        'items'       => [
                            '$ref' => '#/definitions/RecordResponse',
                        ],
                    ],
                    'meta'   => [
                        '$ref' => '#/definitions/Metadata',
                    ],
                ],
            ],
            'Metadata'        => [
                'type'       => 'object',
                'properties' => [
                    'schema' => [
                        'type'        => 'array',
                        'description' => 'Array of table schema.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                    'count'  => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Record count returned for GET requests.',
                    ],
                ],
            ]
        ];
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $base = parent::getApiDocInfo($service, $resource);

        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            $path . '/{table_name}'      => [
                'parameters' => [
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'Records() - Retrieve one or more records.',
                    'operationId'       => 'get' . $capitalized . 'Records',
                    'description'       =>
                        'Set the <b>filter</b> parameter to a SQL WHERE clause (optional native filter accepted in some scenarios) ' .
                        'to limit records returned or leave it blank to return all records up to the maximum limit.<br/> ' .
                        'Set the <b>limit</b> parameter with or without a filter to return a specific amount of records.<br/> ' .
                        'Use the <b>offset</b> parameter along with the <b>limit</b> parameter to page through sets of records.<br/> ' .
                        'Set the <b>order</b> parameter to SQL ORDER_BY clause containing field and optional direction (<field_name> [ASC|DESC]) to order the returned records.<br/> ' .
                        'Alternatively, to send the <b>filter</b> with or without <b>params</b> as posted data, ' .
                        'use the getRecordsByPost() POST request and post a filter with or without params.<br/>' .
                        'Pass the identifying field values as a comma-separated list in the <b>ids</b> parameter.<br/> ' .
                        'Use the <b>id_field</b> and <b>id_type</b> parameters to override or specify detail for identifying fields where applicable.<br/> ' .
                        'Alternatively, to send the <b>ids</b> as posted data, use the getRecordsByPost() POST request.<br/> ' .
                        'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                        'By default, all fields are returned for all records. ',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                        ApiOptions::documentOption(ApiOptions::LIMIT),
                        ApiOptions::documentOption(ApiOptions::OFFSET),
                        ApiOptions::documentOption(ApiOptions::ORDER),
                        ApiOptions::documentOption(ApiOptions::GROUP),
                        ApiOptions::documentOption(ApiOptions::COUNT_ONLY),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_COUNT),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                        ApiOptions::documentOption(ApiOptions::CONTINUES),
                        ApiOptions::documentOption(ApiOptions::ROLLBACK),
                        ApiOptions::documentOption(ApiOptions::FILE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'post'       => [
                    'tags'              => [$serviceName],
                    'summary'           => 'create' . $capitalized . 'Records() - Create one or more records.',
                    'operationId'       => 'create' . $capitalized . 'Records',
                    'description'       =>
                        'Posted data should be an array of records wrapped in a <b>record</b> element.<br/> ' .
                        'By default, only the id property of the record is returned on success. ' .
                        'Use <b>fields</b> parameter to return more info.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'Data containing name-value pairs of records to create.',
                                'in'          => 'body',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'required'    => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            [
                                'name'        => 'X-HTTP-METHOD',
                                'description' => 'Override request using POST to tunnel other http request, such as DELETE or GET passing a payload.',
                                'enum'        => ['GET', 'PUT', 'PATCH', 'DELETE'],
                                'type'        => 'string',
                                'in'          => 'header',
                            ],
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'put'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'replace' .
                        $capitalized .
                        'Records() - Update (replace) one or more records.',
                    'operationId'       => 'replace' . $capitalized . 'Records',
                    'description'       =>
                        'Post data should be an array of records wrapped in a <b>' .
                        $wrapper .
                        '</b> tag.<br/> ' .
                        'If ids or filter is used, posted body should be a single record with name-value pairs ' .
                        'to update, wrapped in a <b>' .
                        $wrapper .
                        '</b> tag.<br/> ' .
                        'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                        'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                        'By default, only the id property of the record is returned on success. ' .
                        'Use <b>fields</b> parameter to return more info.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'Data containing name-value pairs of records to update.',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'in'          => 'body',
                                'required'    => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'patch'      => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' . $capitalized . 'Records() - Update (patch) one or more records.',
                    'operationId'       => 'update' . $capitalized . 'Records',
                    'description'       =>
                        'Post data should be an array of records containing at least the identifying fields for each record.<br/> ' .
                        'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                        'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                        'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                        'By default, only the id property of the record is returned on success. ' .
                        'Use <b>fields</b> parameter to return more info.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'A single record containing name-value pairs of fields to update.',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'in'          => 'body',
                                'required'    => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' . $capitalized . 'Records() - Delete one or more records.',
                    'operationId'       => 'delete' . $capitalized . 'Records',
                    'description'       =>
                        'Set the <b>ids</b> parameter to a list of record identifying (primary key) values to delete specific records.<br/> ' .
                        'Alternatively, to delete records by a large list of ids, pass the ids in the <b>body</b>.<br/> ' .
                        'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ' .
                        'Set the <b>filter</b> parameter to a SQL WHERE clause to delete specific records, ' .
                        'otherwise set <b>force</b> to true to clear the table.<br/> ' .
                        'Alternatively, to delete by a complicated filter or to use parameter replacement, pass the filter with or without params as the <b>body</b>.<br/> ' .
                        'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ' .
                        'Set the <b>body</b> to an array of records, minimally including the identifying fields, to delete specific records.<br/> ' .
                        'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'Data containing ids of records to delete.',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'in'          => 'body',
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                            ApiOptions::documentOption(ApiOptions::FORCE),
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
            ],
            $path . '/{table_name}/{id}' => [
                'parameters' => [
                    [
                        'name'        => 'id',
                        'description' => 'Identifier of the record to retrieve.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'Record() - Retrieve one record by identifier.',
                    'operationId'       => 'get' . $capitalized . 'Record',
                    'description'       =>
                        'Use the <b>fields</b> parameter to limit properties that are returned. ' .
                        'By default, all fields are returned.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'put'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'replace' .
                        $capitalized .
                        'Record() - Replace the content of one record by identifier.',
                    'operationId'       => 'replace' . $capitalized . 'Record',
                    'description'       =>
                        'Post data should be an array of fields for a single record.<br/> ' .
                        'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of the replacement record.',
                            'schema'      => ['$ref' => '#/definitions/RecordRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'patch'      => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' .
                        $capitalized .
                        'Record() - Update (patch) one record by identifier.',
                    'operationId'       => 'update' . $capitalized . 'Record',
                    'description'       =>
                        'Post data should be an array of fields for a single record.<br/> ' .
                        'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of the fields to update.',
                            'schema'      => ['$ref' => '#/definitions/RecordRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' . $capitalized . 'Record() - Delete one record by identifier.',
                    'operationId'       => 'delete' . $capitalized . 'Record',
                    'description'       => 'Use the <b>fields</b> parameter to return more deleted properties. By default, the id is returned.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
            ],
        ];

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], static::getApiDocModels());

        return $base;
    }
}