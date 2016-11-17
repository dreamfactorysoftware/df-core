<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\DataValidator;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\RelationSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DbResourceTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Resources\System\Event;
use DreamFactory\Core\Services\Swagger;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;

class DbSchemaResource extends BaseDbResource
{
    use DataValidator;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Resource tag for dealing with table schema
     */
    const RESOURCE_NAME = '_schema';
    /**
     * Replacement tag for dealing with table schema events
     */
    const EVENT_IDENTIFIER = '{table_name}';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var mixed Resource ID.
     */
    protected $resourceId2;

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
            if (!empty($this->getPermissions($table->name))) {
                $resources[] = $table->name;
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
            $access = $this->getPermissions($table->name);
            if (!empty($access)) {
                $info = $table->toArray();
                $info['access'] = VerbsMask::maskToArray($access);
                $resources[] = $info;
            }
        }

        return $resources;
    }

    /**
     * @inheritdoc
     */
    protected function setResourceMembers($resourcePath = null)
    {
        parent::setResourceMembers($resourcePath);
        if (!empty($this->resourceArray)) {
            if (null !== ($id = array_get($this->resourceArray, 2))) {
                $this->resourceId2 = $id;
            }
        }

        return $this;
    }

    /**
     * Refreshes all schema associated with this db connection:
     */
    public function refreshCachedTables()
    {
        $this->parent->refreshTableCache();
        // Any changes to tables needs to produce a new event list
        Event::clearCache();
        Swagger::clearCache($this->getServiceName());
    }

    /**
     * @param string $table
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateSchemaAccess($table, $action = null)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        $this->doesTableExist($table);
        $this->checkPermission($action, $table);
    }

    /**
     * @inheritdoc
     */
    protected function getEventName()
    {
        $suffix = '';
        switch (count($this->resourceArray)) {
            case 1:
                $suffix = '.' . static::EVENT_IDENTIFIER;
                break;
            case 2:
                $suffix = '.' . static::EVENT_IDENTIFIER . '.{field}';
                break;
            default:
                break;
        }

        return parent::getEventName() . $suffix;
    }


    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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
     * @return array|bool
     * @throws BadRequestException
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $refresh = $this->request->getParameterAsBool(ApiOptions::REFRESH);
        $payload = $this->request->getPayloadData();
        $ids = array_get($payload, ApiOptions::IDS, $this->request->getParameter(ApiOptions::IDS));
        if (empty($ids)) {
            $ids = ResourcesWrapper::unwrapResources($this->request->getPayloadData());
        }

        if (empty($this->resource)) {
            if (!empty($ids)) {
                $result = $this->describeTables($ids, $refresh);
                $result = ResourcesWrapper::wrapResources($result);
            } else {
                $result = parent::handleGET();
            }
        } else {
            if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
                throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
            }

            if (empty($this->resourceId)) {
                $result = $this->describeTable($tableName, $refresh);
            } elseif (empty($this->resourceId2)) {
                switch ($this->resourceId) {
                    case '_field':
                        $result = $this->describeFields($tableName, $ids, $refresh);
                        $result = ResourcesWrapper::wrapResources($result);
                        break;
                    case '_related':
                        $result = $this->describeRelationships($tableName, $ids, $refresh);
                        $result = ResourcesWrapper::wrapResources($result);
                        break;
                    default:
                        // deprecated field describe
                        $result = $this->describeField($tableName, $this->resourceId, $refresh);
                        break;
                }
            } else {
                switch ($this->resourceId) {
                    case '_field':
                        $result = $this->describeField($tableName, $this->resourceId2, $refresh);
                        break;
                    case '_related':
                        $result = $this->describeRelationship($tableName, $this->resourceId2, $refresh);
                        break;
                    default:
                        throw new BadRequestException('Invalid schema path in describe request.');
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handlePOST()
    {
        $payload = $this->request->getPayloadData();
        $checkExist = $this->request->getParameterAsBool('check_exist');
        $fields = $this->request->getParameter(ApiOptions::FIELDS);
        if (empty($this->resource)) {
            $payload = ResourcesWrapper::unwrapResources($payload);
            if (empty($payload)) {
                throw new BadRequestException('No data in schema create request.');
            }

            $result = $this->createTables($payload, $checkExist, $fields);
            $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
            $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
            $result = ResourcesWrapper::cleanResources($result, $asList, $idField, $fields);
        } else {
            if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
                throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
            }

            if (empty($this->resourceId)) {
                $result = $this->createTable($tableName, $payload, $checkExist, $fields);
            } elseif (empty($this->resourceId2)) {
                if (empty($payload)) {
                    throw new BadRequestException('No data in schema create request.');
                }

                switch ($this->resourceId) {
                    case '_field':
                        $payload = ResourcesWrapper::unwrapResources($payload);
                        if (empty($payload)) {
                            throw new BadRequestException('No data in schema create request.');
                        }

                        $result = $this->createFields($tableName, $payload, $checkExist, $fields);
                        break;
                    case '_related':
                        $payload = ResourcesWrapper::unwrapResources($payload);
                        if (empty($payload)) {
                            throw new BadRequestException('No data in schema create request.');
                        }

                        $result = $this->createRelationships($tableName, $payload, $checkExist, $fields);
                        break;
                    default:
                        // deprecated field create
                        $result = $this->createField($tableName, $this->resourceId, $payload, $checkExist, $fields);
                        break;
                }
            } else {
                if (empty($payload)) {
                    throw new BadRequestException('No data in schema create request.');
                }

                switch ($this->resourceId) {
                    case '_field':
                        $result = $this->createField($tableName, $this->resourceId2, $payload, $checkExist, $fields);
                        break;
                    case '_related':
                        $result = $this->createRelationship($tableName, $this->resourceId2, $payload, $checkExist,
                            $fields);
                        break;
                    default:
                        throw new BadRequestException('Invalid schema path in create request.');
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handlePUT()
    {
        $payload = $this->request->getPayloadData();
        $fields = $this->request->getParameter(ApiOptions::FIELDS);
        if (empty($this->resource)) {
            $payload = ResourcesWrapper::unwrapResources($payload);
            if (empty($payload)) {
                throw new BadRequestException('No data in schema update request.');
            }

            $result = $this->updateTables($payload, true, $fields);
            $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
            $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
            $result = ResourcesWrapper::cleanResources($result, $asList, $idField, $fields);
        } else {
            if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
                throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
            }

            if (empty($this->resourceId)) {
                $result = $this->updateTable($tableName, $payload, true, $fields);
            } elseif (empty($this->resourceId2)) {
                if (empty($payload)) {
                    throw new BadRequestException('No data in schema update request.');
                }

                switch ($this->resourceId) {
                    case '_field':
                        $payload = ResourcesWrapper::unwrapResources($payload);
                        if (empty($payload)) {
                            throw new BadRequestException('No data in schema update request.');
                        }

                        $result = $this->updateFields($tableName, $payload, true, $fields);
                        break;
                    case '_related':
                        $payload = ResourcesWrapper::unwrapResources($payload);
                        if (empty($payload)) {
                            throw new BadRequestException('No data in schema update request.');
                        }

                        $result = $this->updateRelationships($tableName, $payload, true, $fields);
                        break;
                    default:
                        // deprecated field update
                        $result = $this->updateField($tableName, $this->resourceId, $payload, true, $fields);
                        break;
                }
            } else {
                if (empty($payload)) {
                    throw new BadRequestException('No data in schema update request.');
                }

                switch ($this->resourceId) {
                    case '_field':
                        $result = $this->updateField($tableName, $this->resourceId2, $payload, true, $fields);
                        break;
                    case '_related':
                        $result = $this->updateRelationship($tableName, $this->resourceId2, $payload, true, $fields);
                        break;
                    default:
                        throw new BadRequestException('Invalid schema path in update request.');
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handlePATCH()
    {
        $payload = $this->request->getPayloadData();
        $fields = $this->request->getParameter(ApiOptions::FIELDS);
        if (empty($this->resource)) {
            $tables = ResourcesWrapper::unwrapResources($payload);
            if (empty($tables)) {
                throw new BadRequestException('No data in schema update request.');
            }

            $result = $this->updateTables($tables, false, $fields);
            $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
            $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
            $result = ResourcesWrapper::cleanResources($result, $asList, $idField, $fields);
        } else {
            if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
                throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
            }

            if (empty($this->resourceId)) {
                $result = $this->updateTable($tableName, $payload, false, $fields);
            } elseif (empty($this->resourceId2)) {
                if (empty($payload)) {
                    throw new BadRequestException('No data in schema update request.');
                }

                switch ($this->resourceId) {
                    case '_field':
                        $payload = ResourcesWrapper::unwrapResources($payload);
                        if (empty($payload)) {
                            throw new BadRequestException('No data in schema update request.');
                        }

                        $result = $this->updateFields($tableName, $payload, false, $fields);
                        break;
                    case '_related':
                        $payload = ResourcesWrapper::unwrapResources($payload);
                        if (empty($payload)) {
                            throw new BadRequestException('No data in schema update request.');
                        }

                        $result = $this->updateRelationships($tableName, $payload, false, $fields);
                        break;
                    default:
                        // deprecated field update
                        $result = $this->updateField($tableName, $this->resourceId, $payload, false, $fields);
                        break;
                }
            } else {
                if (empty($payload)) {
                    throw new BadRequestException('No data in schema update request.');
                }

                switch ($this->resourceId) {
                    case '_field':
                        $result = $this->updateField($tableName, $this->resourceId2, $payload, false, $fields);
                        break;
                    case '_related':
                        $result = $this->updateRelationship($tableName, $this->resourceId2, $payload, false, $fields);
                        break;
                    default:
                        throw new BadRequestException('Invalid schema path in update request.');
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handleDELETE()
    {
        $payload = $this->request->getPayloadData();
        $fields = $this->request->getParameter(ApiOptions::FIELDS);
        if (empty($this->resource)) {
            $tables = $this->request->getParameter(ApiOptions::IDS);
            if (empty($tables)) {
                $tables = ResourcesWrapper::unwrapResources($payload);
            }

            if (empty($tables)) {
                throw new BadRequestException('No data in schema delete request.');
            }

            $result = $this->deleteTables($tables);
            $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
            $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
            $result = ResourcesWrapper::cleanResources($result, $asList, $idField, $fields);
        } else {
            if (false === ($tableName = $this->doesTableExist($this->resource, true))) {
                throw new NotFoundException('Table "' . $this->resource . '" does not exist in the database.');
            }

            if (empty($this->resourceId)) {
                // delete the table
                $this->deleteTable($tableName);
            } elseif (empty($this->resourceId2)) {
                switch ($this->resourceId) {
                    case '_field':
                        $ids = array_get($payload, ApiOptions::IDS, $this->request->getParameter(ApiOptions::IDS));
                        if (empty($ids)) {
                            if (empty($ids = ResourcesWrapper::unwrapResources($payload))) {
                                throw new BadRequestException('No data in schema delete request.');
                            }
                        }

                        $this->deleteFields($tableName, $ids);
                        break;
                    case '_related':
                        $ids = array_get($payload, ApiOptions::IDS, $this->request->getParameter(ApiOptions::IDS));
                        if (empty($ids)) {
                            if (empty($ids = ResourcesWrapper::unwrapResources($payload))) {
                                throw new BadRequestException('No data in schema delete request.');
                            }
                        }

                        $this->deleteRelationships($tableName, $ids);
                        break;
                    default:
                        // deprecated field delete
                        $this->deleteField($tableName, $this->resourceId);
                        break;
                }
            } else {
                switch ($this->resourceId) {
                    case '_field':
                        $this->deleteField($tableName, $this->resourceId2);
                        break;
                    case '_related':
                        $this->deleteRelationship($tableName, $this->resourceId2);
                        break;
                    default:
                        throw new BadRequestException('Invalid schema path in delete request.');
                        break;
                }
            }

            $result = ['success' => true];
        }

        return $result;
    }

    /**
     * Get multiple tables and their properties
     *
     * @param string | array $tables  Table names comma-delimited string or array
     * @param bool           $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function describeTables($tables, $refresh = false)
    {
        $tables = static::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $out = [];
        foreach ($tables as $table) {
            $name = (is_array($table)) ? array_get($table, 'name') : $table;
            $this->validateSchemaAccess($name, Verbs::GET);

            $out[] = $this->describeTable($table, $refresh);
        }

        return $out;
    }

    /**
     * Get any properties related to the table
     *
     * @param string | array $name   Table name or defining properties
     * @param bool           $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function describeTable($name, $refresh = false)
    {
        $name = (is_array($name) ? array_get($name, 'name') : $name);
        if (empty($name)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        try {
            $table = $this->parent->getSchema()->getResource(DbResourceTypes::TYPE_TABLE, $name, $refresh);
            if (!$table) {
                throw new NotFoundException("Table '$name' does not exist in the database.");
            }

            $result = $table->toArray();
            $result['access'] = $this->getPermissions($name);

            return $result;
        } catch (RestException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to query database schema.\n{$ex->getMessage()}");
        }
    }

    /**
     * Describe multiple table fields
     *
     * @param string $table
     * @param array  $fields
     * @param bool   $refresh      Force a refresh of the schema from the database
     *
     * @throws \Exception
     * @return array
     */
    public function describeFields($table, $fields, $refresh = false)
    {
        if (empty($fields)) {
            $out = $this->describeTable($table, $refresh);

            return array_get($out, 'field');
        }

        $fields = static::validateAsArray(
            $fields,
            ',',
            true,
            'The request contains no valid table field names or properties.'
        );

        $out = [];
        foreach ($fields as $field) {
            $name = (is_array($field)) ? array_get($field, 'name') : $field;
            $this->validateSchemaAccess($table, Verbs::GET);
            $out[] = $this->describeField($table, $name, $refresh);
        }

        return $out;
    }

    /**
     * Get any properties related to the table field
     *
     * @param string $table   Table name
     * @param string $field   Table field name
     * @param bool   $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function describeField($table, $field, $refresh = false)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        try {
            $result = $this->describeTableFields($table, $field, $refresh);

            return array_get($result, 0);
        } catch (RestException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error describing database table '$table' field '$field'.\n" .
                $ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * Describe multiple table fields
     *
     * @param string $table
     * @param array  $relationships
     * @param bool   $refresh      Force a refresh of the schema from the database
     *
     * @throws \Exception
     * @return array
     */
    public function describeRelationships($table, $relationships, $refresh = false)
    {
        if (empty($fields)) {
            $out = $this->describeTable($table, $refresh);

            return array_get($out, 'related');
        }

        $relationships = static::validateAsArray(
            $relationships,
            ',',
            true,
            'The request contains no valid table field names or properties.'
        );

        $out = [];
        foreach ($relationships as $relationship) {
            $name = (is_array($relationship)) ? array_get($relationship, 'name') : $relationship;
            $this->validateSchemaAccess($table, Verbs::GET);
            $out[] = $this->describeRelationship($table, $name, $refresh);
        }

        return $out;
    }

    /**
     * Get any properties related to the table field
     *
     * @param string $table        Table name
     * @param string $relationship Table relationship name
     * @param bool   $refresh      Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function describeRelationship($table, $relationship, $refresh = false)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        try {
            $result = $this->describeTableRelationships($table, $relationship, $refresh);

            return array_get($result, 0);
        } catch (RestException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error describing database table '$table' relationship '$relationship'.\n" .
                $ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * Create one or more tables by array of table properties
     *
     * @param string|array $tables
     * @param bool         $check_exist
     * @param bool         $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function createTables($tables, $check_exist = false, $return_schema = false)
    {
        $tables = static::validateAsArray($tables, null, true, 'There are no table sets in the request.');

        foreach ($tables as $table) {
            if (null === ($name = array_get($table, 'name'))) {
                throw new BadRequestException("Table schema received does not have a valid name.");
            }
        }

        $result = $this->parent->getSchema()->updateSchema($tables, !$check_exist);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeTables($tables);
        }

        return $result;
    }

    /**
     * Create a single table by name and additional properties
     *
     * @param string $table
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     * @return array|mixed
     */
    public function createTable($table, $properties = [], $check_exist = false, $return_schema = false)
    {
        $properties = (is_array($properties) ? $properties : []);
        $properties['name'] = $table;

        $tables = static::validateAsArray($properties, null, true, 'Bad data format in request.');
        $result = $this->parent->getSchema()->updateSchema($tables, !$check_exist);
        $result = array_get($result, 0, []);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeTable($table);
        }

        return $result;
    }

    /**
     * Create multiple table fields
     *
     * @param string $table
     * @param array  $fields
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @throws \Exception
     * @return array
     */
    public function createFields($table, $fields, $check_exist = false, $return_schema = false)
    {
        $fields = static::validateAsArray(
            $fields,
            ',',
            true,
            'The request contains no valid table field names or properties.'
        );

        $out = [];
        foreach ($fields as $field) {
            $name = (is_array($field)) ? array_get($field, 'name') : $field;
            $this->validateSchemaAccess($table, Verbs::PUT);
            $out[] = $this->createField($table, $name, $field, $check_exist, $return_schema);
        }

        return $out;
    }

    /**
     * Create a single table field by name and additional properties
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     * @return array|mixed
     */
    public function createField($table, $field, $properties = [], $check_exist = false, $return_schema = false)
    {
        $properties = (is_array($properties) ? $properties : []);
        $properties['name'] = $field;

        $fields = static::validateAsArray($properties, null, true, 'Bad data format in request.');

        $tables = [['name' => $table, 'field' => $fields]];
        $result = $this->parent->getSchema()->updateSchema($tables, !$check_exist);
        $result = array_get(array_get($result, 0, []), 'field', []);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeField($table, $field);
        }

        return $result;
    }

    /**
     * Create multiple table relationships
     *
     * @param string $table
     * @param array  $relationships
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @throws \Exception
     * @return array
     */
    public function createRelationships($table, $relationships, $check_exist = false, $return_schema = false)
    {
        $relationships = static::validateAsArray(
            $relationships,
            ',',
            true,
            'The request contains no valid table relationship names or properties.'
        );

        $out = [];
        foreach ($relationships as $relationship) {
            $name = (is_array($relationship)) ? array_get($relationship, 'name') : $relationship;
            $this->validateSchemaAccess($table, Verbs::PUT);
            $out[] = $this->createRelationship($table, $name, $relationship, $check_exist, $return_schema);
        }

        return $out;
    }

    /**
     * Create a single table relationship by name and additional properties
     *
     * @param string $table
     * @param string $relationship
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     * @return array|mixed
     */
    public function createRelationship(
        $table,
        $relationship,
        $properties = [],
        $check_exist = false,
        $return_schema = false
    ) {
        $properties = (is_array($properties) ? $properties : []);
        $properties['name'] = $relationship;

        $fields = static::validateAsArray($properties, null, true, 'Bad data format in request.');

        $tables = [['name' => $table, 'related' => $fields]];
        $result = $this->parent->getSchema()->updateSchema($tables, !$check_exist);
        $result = array_get(array_get($result, 0, []), 'related', []);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeRelationship($table, $relationship);
        }

        return $result;
    }

    /**
     * Update one or more tables by array of table properties
     *
     * @param array $tables
     * @param bool  $allow_delete_fields
     * @param bool  $return_schema Return a refreshed copy of the schema from the database
     * @return array
     * @throws BadRequestException
     */
    public function updateTables($tables, $allow_delete_fields = false, $return_schema = false)
    {
        $tables = static::validateAsArray($tables, null, true, 'There are no table sets in the request.');

        foreach ($tables as $table) {
            $name = (is_array($table)) ? array_get($table, 'name') : $table;
            if (empty($name)) {
                throw new BadRequestException("Table schema received does not have a valid name.");
            }
            if ($this->doesTableExist($name)) {
                $this->validateSchemaAccess($name, Verbs::PATCH);
            } else {
                $this->validateSchemaAccess(null, Verbs::POST);
            }
        }

        $result = $this->parent->getSchema()->updateSchema($tables, true, $allow_delete_fields);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeTables($tables);
        }

        return $result;
    }

    /**
     * Update properties related to the table
     *
     * @param string $table
     * @param array  $properties
     * @param bool   $allow_delete_fields
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function updateTable($table, $properties, $allow_delete_fields = false, $return_schema = false)
    {
        $properties = (is_array($properties) ? $properties : []);
        $properties['name'] = $table;

        $tables = static::validateAsArray($properties, null, true, 'Bad data format in request.');

        $result = $this->parent->getSchema()->updateSchema($tables, true, $allow_delete_fields);
        $result = array_get($result, 0, []);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeTable($table);
        }

        return $result;
    }

    /**
     * Update multiple table fields
     *
     * @param string $table
     * @param array  $fields
     * @param bool   $allow_delete_parts
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @throws \Exception
     * @return array
     */
    public function updateFields($table, $fields, $allow_delete_parts = false, $return_schema = false)
    {
        $fields = static::validateAsArray(
            $fields,
            ',',
            true,
            'The request contains no valid table field names or properties.'
        );

        $out = [];
        foreach ($fields as $field) {
            $name = (is_array($field)) ? array_get($field, 'name') : $field;
            $this->validateSchemaAccess($table, Verbs::PUT);
            $out[] = $this->updateField($table, $name, $field, $allow_delete_parts, $return_schema);
        }

        return $out;
    }

    /**
     * Update properties related to a table field
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $allow_delete_parts
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function updateField($table, $field, $properties = [], $allow_delete_parts = false, $return_schema = false)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        $properties = (is_array($properties) ? $properties : []);
        $properties['name'] = $field;

        $fields = static::validateAsArray($properties, null, true, 'Bad data format in request.');

        $tables = [['name' => $table, 'field' => $fields]];
        $result = $this->parent->getSchema()->updateSchema($tables, true, $allow_delete_parts);
        $result = array_get(array_get($result, 0, []), 'field', []);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeField($table, $field);
        }

        return $result;
    }

    /**
     * Update multiple table relationships
     *
     * @param string $table
     * @param array  $relationships
     * @param bool   $allow_delete_parts
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @throws \Exception
     * @return array
     */
    public function updateRelationships($table, $relationships, $allow_delete_parts = false, $return_schema = false)
    {
        $relationships = static::validateAsArray(
            $relationships,
            ',',
            true,
            'The request contains no valid table relationship names or properties.'
        );

        $out = [];
        foreach ($relationships as $relationship) {
            $name = (is_array($relationship)) ? array_get($relationship, 'name') : $relationship;
            $this->validateSchemaAccess($table, Verbs::PUT);
            $out[] = $this->updateRelationship($table, $name, $relationship, $allow_delete_parts, $return_schema);
        }

        return $out;
    }

    /**
     * Update properties related to a table relationship
     *
     * @param string $table
     * @param string $relationship
     * @param array  $properties
     * @param bool   $allow_delete_parts
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function updateRelationship(
        $table,
        $relationship,
        $properties = [],
        $allow_delete_parts = false,
        $return_schema = false
    ) {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        $properties = (is_array($properties) ? $properties : []);
        $properties['name'] = $relationship;

        $fields = static::validateAsArray($properties, null, true, 'Bad data format in request.');

        $tables = [['name' => $table, 'related' => $fields]];
        $result = $this->parent->getSchema()->updateSchema($tables, true, $allow_delete_parts);
        $result = array_get(array_get($result, 0, []), 'related', []);

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        if ($return_schema) {
            return $this->describeRelationship($table, $relationship);
        }

        return $result;
    }

    /**
     * Delete multiple tables and all of their contents
     *
     * @param array $tables
     * @param bool  $check_empty
     *
     * @return array
     * @throws \Exception
     */
    public function deleteTables($tables, $check_empty = false)
    {
        $tables = static::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $out = [];
        foreach ($tables as $table) {
            $name = (is_array($table)) ? array_get($table, 'name') : $table;
            $this->validateSchemaAccess($name, Verbs::DELETE);
            $out[] = $this->deleteTable($name, $check_empty);
        }

        return $out;
    }

    /**
     * Delete a table and all of its contents by name
     *
     * @param string $table
     * @param bool   $check_empty
     *
     * @throws \Exception
     * @return array
     */
    public function deleteTable($table, $check_empty = false)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        //  Does it exist
        if (!$this->doesTableExist($table)) {
            throw new NotFoundException("Table '$table' not found.");
        }

        if ($check_empty) {
            // todo exist query here
        }
        try {
            $this->parent->getSchema()->dropResource(DbResourceTypes::TYPE_TABLE, $table);
        } catch (\Exception $ex) {
            \Log::error('Exception dropping table: ' . $ex->getMessage());

            throw $ex;
        }

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        return ['name' => $table];
    }

    /**
     * Delete multiple table fields
     *
     * @param string $table
     * @param array  $fields
     *
     * @throws \Exception
     * @return array
     */
    public function deleteFields($table, $fields)
    {
        $fields = static::validateAsArray(
            $fields,
            ',',
            true,
            'The request contains no valid table field names or properties.'
        );

        $out = [];
        foreach ($fields as $field) {
            $name = (is_array($field)) ? array_get($field, 'name') : $field;
            $this->validateSchemaAccess($table, Verbs::DELETE);
            $out[] = $this->deleteField($table, $name);
        }

        return $out;
    }

    /**
     * Delete a table field
     *
     * @param string $table
     * @param string $field
     *
     * @throws \Exception
     * @return array
     */
    public function deleteField($table, $field)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        // does it already exist
        if (!$this->doesTableExist($table)) {
            throw new NotFoundException("A table with name '$table' does not exist in the database.");
        }

        try {
            $this->parent->getSchema()->dropResource(DbResourceTypes::TYPE_TABLE_FIELD, [$table, $field]);
        } catch (\Exception $ex) {
            error_log($ex->getMessage());
            throw $ex;
        }

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        return ['name' => $field];
    }

    /**
     * Delete multiple table fields
     *
     * @param string $table
     * @param array  $relationships
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRelationships($table, $relationships)
    {
        $relationships = static::validateAsArray(
            $relationships,
            ',',
            true,
            'The request contains no valid table field names or properties.'
        );

        $out = [];
        foreach ($relationships as $relationship) {
            $name = (is_array($relationship)) ? array_get($relationship, 'name') : $relationship;
            $this->validateSchemaAccess($table, Verbs::DELETE);
            $out[] = $this->deleteRelationship($table, $name);
        }

        return $out;
    }

    /**
     * Delete a table field
     *
     * @param string $table
     * @param string $relationship
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRelationship($table, $relationship)
    {
        if (empty($table)) {
            throw new BadRequestException('Table name can not be empty.');
        }

        // does it already exist
        if (!$this->doesTableExist($table)) {
            throw new NotFoundException("A table with name '$table' does not exist in the database.");
        }

        try {
            $this->parent->getSchema()->dropResource(DbResourceTypes::TYPE_TABLE_RELATIONSHIP, [$table, $relationship]);
        } catch (\Exception $ex) {
            error_log($ex->getMessage());
            throw $ex;
        }

        //  Any changes here should refresh cached schema
        $this->refreshCachedTables();

        return ['name' => $relationship];
    }

    /**
     * @param string $name
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @return string
     */
    public function correctTableName(&$name)
    {
        if (false !== ($table = $this->doesTableExist($name, true))) {
            $name = $table;

            return $name;
        } else {
            throw new NotFoundException('Table "' . $name . '" does not exist in the database.');
        }
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
        return $this->parent->getSchema()->doesResourceExist(DbResourceTypes::TYPE_TABLE, $name, $returnName);
    }


    /**
     * @param string                $table_name
     * @param null | string | array $field_names
     * @param bool                  $refresh
     *
     * @throws NotFoundException
     * @throws InternalServerErrorException
     * @return array
     */
    public function describeTableFields($table_name, $field_names = null, $refresh = false)
    {
        $table = $this->parent->getSchema()->getResource(DbResourceTypes::TYPE_TABLE, $table_name, $refresh);
        if (!$table) {
            throw new NotFoundException("Table '$table_name' does not exist in the database.");
        }

        if (!empty($field_names)) {
            $field_names = static::validateAsArray($field_names, ',', true, 'No valid field names given.');
        }

        $out = [];
        try {
            /** @var ColumnSchema $column */
            foreach ($table->columns as $column) {
                if (empty($field_names) || (false !== array_search($column->name, $field_names))) {
                    $out[] = $column->toArray();
                }
            }
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to query table field schema.\n{$ex->getMessage()}");
        }

        if (empty($out)) {
            throw new NotFoundException("No requested fields found in table '$table_name'.");
        }

        return $out;
    }

    /**
     * @param string                $table_name
     * @param null | string | array $relationships
     * @param bool                  $refresh
     *
     * @throws NotFoundException
     * @throws InternalServerErrorException
     * @return array
     */
    public function describeTableRelationships($table_name, $relationships = null, $refresh = false)
    {
        /** @var TableSchema $table */
        $table = $this->parent->getSchema()->getResource(DbResourceTypes::TYPE_TABLE, $table_name, $refresh);
        if (!$table) {
            throw new NotFoundException("Table '$table_name' does not exist in the database.");
        }

        if (!empty($relationships)) {
            $relationships = static::validateAsArray($relationships, ',', true, 'No valid relationship names given.');
        }

        $out = [];
        try {
            /** @var RelationSchema $relation */
            foreach ($table->relations as $relation) {
                if (empty($relationships) || (false !== array_search($relation->name, $relationships))) {
                    $out[] = $relation->toArray();
                }
            }
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to query table relationship schema.\n{$ex->getMessage()}");
        }

        if (empty($out)) {
            throw new NotFoundException("No requested relationships found in table '$table_name'.");
        }

        return $out;
    }

    /**
     * @inheritdoc
     */
    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = Inflector::pluralize($class);
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $base = parent::getApiDocInfo($service, $resource);

        $add = [
            'post'  => [
                'tags'        => [$serviceName],
                'summary'     => 'create' . $capitalized . 'Tables() - Create one or more tables.',
                'operationId' => 'create' . $capitalized . 'Tables',
                'parameters'  => [
                    [
                        'name'        => 'tables',
                        'description' => 'Array of table definitions.',
                        'schema'      => ['$ref' => '#/definitions/TableSchemas'],
                        'in'          => 'body',
                        'required'    => true,
                    ],
                ],
                'responses'   => [
                    '200'     => [
                        'description' => 'Tables Created',
                        'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
                    ],
                    'default' => [
                        'description' => 'Error',
                        'schema'      => ['$ref' => '#/definitions/Error']
                    ]
                ],
                'description' => 'Post data should be a single table definition or an array of table definitions.',
            ],
            'put'   => [
                'tags'        => [$serviceName],
                'summary'     => 'replace' . $capitalized . 'Tables() - Update (replace) one or more tables.',
                'operationId' => 'replace' . $capitalized . 'Tables',
                'parameters'  => [
                    [
                        'name'        => 'tables',
                        'description' => 'Array of table definitions.',
                        'schema'      => ['$ref' => '#/definitions/TableSchemas'],
                        'in'          => 'body',
                        'required'    => true,
                    ],
                ],
                'responses'   => [
                    '200'     => [
                        'description' => 'Tables Updated',
                        'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
                    ],
                    'default' => [
                        'description' => 'Error',
                        'schema'      => ['$ref' => '#/definitions/Error']
                    ]
                ],
                'description' => 'Post data should be a single table definition or an array of table definitions.',
            ],
            'patch' => [
                'tags'        => [$serviceName],
                'summary'     => 'update' . $capitalized . 'Tables() - Update (patch) one or more tables.',
                'operationId' => 'update' . $capitalized . 'Tables',
                'parameters'  => [
                    [
                        'name'        => 'tables',
                        'description' => 'Array of table definitions.',
                        'schema'      => ['$ref' => '#/definitions/TableSchemas'],
                        'in'          => 'body',
                        'required'    => true,
                    ],
                ],
                'responses'   => [
                    '200'     => [
                        'description' => 'Tables Updated',
                        'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
                    ],
                    'default' => [
                        'description' => 'Error',
                        'schema'      => ['$ref' => '#/definitions/Error']
                    ]
                ],
                'description' => 'Post data should be a single table definition or an array of table definitions.',
            ],
        ];
        $base['paths'][$path] = array_merge($base['paths'][$path], $add);

        $apis = [
            $path . '/{table_name}'              => [
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
                    'tags'        => [$serviceName],
                    'summary'     => 'describe' .
                        $capitalized .
                        'Table() - Retrieve table definition for the given table.',
                    'operationId' => 'describe' . $capitalized . 'Table',
                    'parameters'  => [
                        [
                            'name'        => 'refresh',
                            'description' => 'Refresh any cached copy of the schema.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Table Schema',
                            'schema'      => ['$ref' => '#/definitions/TableSchema']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'This describes the table, its fields and relations to other tables.',
                ],
                'post'       => [
                    'tags'        => [$serviceName],
                    'summary'     => 'create' .
                        $capitalized .
                        'Table() - Create a table with the given properties and fields.',
                    'operationId' => 'create' . $capitalized . 'Table',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of table properties and fields definitions.',
                            'schema'      => ['$ref' => '#/definitions/TableSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties.',
                ],
                'put'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' .
                        $capitalized .
                        'Table() - Update (replace) a table with the given properties.',
                    'operationId' => 'replace' . $capitalized . 'Table',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of field definitions.',
                            'schema'      => ['$ref' => '#/definitions/TableSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' .
                        $capitalized .
                        'Table() - Update (patch) a table with the given properties.',
                    'operationId' => 'update' . $capitalized . 'Table',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of field definitions.',
                            'schema'      => ['$ref' => '#/definitions/TableSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' . $capitalized . 'Table() - Delete (aka drop) the given table.',
                    'operationId' => 'delete' . $capitalized . 'Table',
                    'parameters'  => [
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Careful, this drops the database table and all of its contents.',
                ],
            ],
            $path . '/{table_name}/_field'              => [
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
                    'tags'        => [$serviceName],
                    'summary'     => 'describe' .
                        $capitalized .
                        'Fields() - Retrieve table field definitions for the given table.',
                    'operationId' => 'describe' . $capitalized . 'Fields',
                    'parameters'  => [
                        [
                            'name'        => 'refresh',
                            'description' => 'Refresh any cached copy of the schema.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Table Fields Schema',
                            'schema'      => ['$ref' => '#/definitions/FieldSchemas']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'This describes the table\'s fields.',
                ],
                'post'       => [
                    'tags'        => [$serviceName],
                    'summary'     => 'create' .
                        $capitalized .
                        'Fields() - Create table fields.',
                    'operationId' => 'create' . $capitalized . 'Fields',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of table properties and fields definitions.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchemas'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of fields and their properties.',
                ],
                'put'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' .
                        $capitalized .
                        'Fields() - Update (replace) table fields with the given properties.',
                    'operationId' => 'replace' . $capitalized . 'Fields',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of field definitions.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchemas'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of fields and their properties.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' .
                        $capitalized .
                        'Fields() - Update (patch) table fields with the given properties.',
                    'operationId' => 'update' . $capitalized . 'Fields',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of field definitions.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchemas'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' . $capitalized . 'Fields() - Delete (aka drop) the given fields.',
                    'operationId' => 'delete' . $capitalized . 'Fields',
                    'parameters'  => [
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Careful, this drops the table column and all of its contents.',
                ],
            ],
            $path . '/{table_name}/_related'              => [
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
                    'tags'        => [$serviceName],
                    'summary'     => 'describe' .
                        $capitalized .
                        'Relationships() - Retrieve relationships definition for the given table.',
                    'operationId' => 'describe' . $capitalized . 'Relationships',
                    'parameters'  => [
                        [
                            'name'        => 'refresh',
                            'description' => 'Refresh any cached copy of the schema.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Relationships Schema',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchemas']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'This describes the table relationships to other tables.',
                ],
                'post'       => [
                    'tags'        => [$serviceName],
                    'summary'     => 'create' .
                        $capitalized .
                        'Relationships() - Create table relationships with the given properties.',
                    'operationId' => 'create' . $capitalized . 'Relationships',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of relationship definitions.',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchemas'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of relationship properties.',
                ],
                'put'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' .
                        $capitalized .
                        'Relationships() - Update (replace) table relationships with the given properties.',
                    'operationId' => 'replace' . $capitalized . 'Relationships',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of field definitions.',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchemas'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of relationship properties.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' .
                        $capitalized .
                        'Relationships() - Update (patch) a table with the given properties.',
                    'operationId' => 'update' . $capitalized . 'Relationships',
                    'parameters'  => [
                        [
                            'name'        => 'schema',
                            'description' => 'Array of field definitions.',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchemas'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of relationship properties.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' . $capitalized . 'Relationships() - Delete the given table relationships.',
                    'operationId' => 'delete' . $capitalized . 'Relationships',
                    'parameters'  => [
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Removes the relationships between tables.',
                ],
            ],
            $path . '/{table_name}/_field/{field_name}' => [
                'parameters' => [
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                    [
                        'name'        => 'field_name',
                        'description' => 'Name of the field to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'describe' .
                        $capitalized .
                        'Field() - Retrieve the definition of the given field for the given table.',
                    'operationId' => 'describe' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'refresh',
                            'description' => 'Refresh any cached copy of the schema.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Field Schema',
                            'schema'      => ['$ref' => '#/definitions/FieldSchema']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'This describes the field and its properties.',
                ],
                'put'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' . $capitalized . 'Field() - Update one field by identifier.',
                    'operationId' => 'replace' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'properties',
                            'description' => 'Array of field properties.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties for the given field.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' . $capitalized . 'Field() - Update one field by identifier.',
                    'operationId' => 'update' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'properties',
                            'description' => 'Array of field properties.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties for the given field.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' .
                        $capitalized .
                        'Field() - Remove the given field from the given table.',
                    'operationId' => 'delete' . $capitalized . 'Field',
                    'parameters'  => [],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Careful, this drops the database table field/column and all of its contents.',
                ],
            ],
            $path . '/{table_name}/_related/{relationship_name}' => [
                'parameters' => [
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                    [
                        'name'        => 'relationship_name',
                        'description' => 'Name of the relationship to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'describe' .
                        $capitalized .
                        'Relationship() - Retrieve the definition of the given relationship for the given table.',
                    'operationId' => 'describe' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'refresh',
                            'description' => 'Refresh any cached copy of the schema.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Relationship Schema',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchema']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'This describes the relationship and its properties.',
                ],
                'put'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' . $capitalized . 'Relationship() - Update one relationship by identifier.',
                    'operationId' => 'replace' . $capitalized . 'Relationship',
                    'parameters'  => [
                        [
                            'name'        => 'properties',
                            'description' => 'Array of relationship properties.',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of properties for the given relationship.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' . $capitalized . 'Relationship() - Update one relationship by identifier.',
                    'operationId' => 'update' . $capitalized . 'Relationship',
                    'parameters'  => [
                        [
                            'name'        => 'properties',
                            'description' => 'Array of relationship properties.',
                            'schema'      => ['$ref' => '#/definitions/RelationshipSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of properties for the given relationship.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' .
                        $capitalized .
                        'Relationship() - Remove the given relationship from the given table.',
                    'operationId' => 'delete' . $capitalized . 'Relationship',
                    'parameters'  => [],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Removes the relationship between the tables given.',
                ],
            ],
            $path . '/{table_name}/{field_name}' => [
                'parameters' => [
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                    [
                        'name'        => 'field_name',
                        'description' => 'Name of the field to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'describe' .
                        $capitalized .
                        'Field() - Retrieve the definition of the given field for the given table. DEPRECATED',
                    'operationId' => 'describe' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'refresh',
                            'description' => 'Refresh any cached copy of the schema.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Field Schema',
                            'schema'      => ['$ref' => '#/definitions/FieldSchema']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'This describes the field and its properties.',
                ],
                'put'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' . $capitalized . 'Field() - Update one field by identifier. DEPRECATED',
                    'operationId' => 'replace' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'properties',
                            'description' => 'Array of field properties.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties for the given field.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' . $capitalized . 'Field() - Update one field by identifier. DEPRECATED',
                    'operationId' => 'update' . $capitalized . 'Field',
                    'parameters'  => [
                        [
                            'name'        => 'properties',
                            'description' => 'Array of field properties.',
                            'schema'      => ['$ref' => '#/definitions/FieldSchema'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Post data should be an array of field properties for the given field.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' .
                        $capitalized .
                        'Field() - Remove the given field from the given table. DEPRECATED',
                    'operationId' => 'delete' . $capitalized . 'Field',
                    'parameters'  => [],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Careful, this drops the database table field/column and all of its contents.',
                ],
            ],
        ];

        $wrapper = ResourcesWrapper::getWrapper();
        $models = [
            'Tables' => [
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
            'Table'  => [
                'type'       => 'object',
                'properties' => [
                    'name' => [
                        'type'        => 'string',
                        'description' => 'Name of the table.',
                    ],
                ],
            ],
        ];

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], $models);

        return $base;
    }
}