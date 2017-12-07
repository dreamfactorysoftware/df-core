<?php

namespace DreamFactory\Core\Components\ResourceImport;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Jobs\DBInsert;
use DreamFactory\Core\Utility\ResourcesWrapper;
use Log;
use ServiceManager;

class CSV implements Importable
{
    /** File extension */
    const FILE_EXTENSION = 'csv';

    /** @var null|string Temp file path */
    protected $file = null;

    /** @var null|string Target service name */
    protected $service = null;

    /** @var null|string Target service group */
    protected $serviceGroupType = null;

    /** @var null|string Target resource */
    protected $resource = null;

    /**
     * CSV constructor.
     *
     * @param string      $file
     * @param string      $service
     * @param null|string $resource
     */
    public function __construct($file, $service, $resource = null)
    {
        $this->file = $file;
        $this->setService($service);
        $this->setResource($resource);
    }

    /**
     * Imports CSV data
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public function import()
    {
        switch ($this->serviceGroupType) {
            case ServiceTypeGroups::DATABASE:
                $header = $this->getHeader();
                if ($this->createDbTableFromHeader($header)) {
                    try {
                        $this->importData();

                        return true;
                    } catch (\Exception $e) {
                        $this->revert();

                        throw $e;
                    }
                }
                break;
            default:
                throw new BadRequestException(
                    'Importing resource(s) to service type group [' . $this->serviceGroupType . '] ' .
                    'is not currently supported.'
                );
        }
    }

    /**
     * Reverts partial import after failure
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function revert()
    {
        switch ($this->serviceGroupType) {
            case ServiceTypeGroups::DATABASE:
                if ($this->resourceExists('_schema/' . $this->resource)) {
                    /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
                    $rs = ServiceManager::handleRequest(
                        $this->service->getName(),
                        Verbs::DELETE,
                        '_schema/' . $this->resource
                    );
                    if ($rs->getStatusCode() === HttpStatusCodes::HTTP_OK) {
                        return true;
                    }
                    $content = $rs->getContent();
                    Log::error(
                        'Failed to delete table on import failure: ' .
                        (is_array($content) ? print_r($content, true) : $content)
                    );
                    throw new InternalServerErrorException('Failed to delete table on import failure. See log for details.');
                }
                break;
            default:
                throw new InternalServerErrorException('An Unexpected error occurred.');
        }
    }

    /**
     * Returns the target resource name
     *
     * @return null|string
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Sets the target service
     *
     * @param $serviceName
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function setService($serviceName)
    {
        if (empty($serviceName)) {
            throw new InternalServerErrorException('No target service name provided for CSV import.');
        }
        $this->service = ServiceManager::getService($serviceName);
        $this->serviceGroupType = $this->service->getServiceTypeInfo()->getGroup();
    }

    /**
     * Sets the target resource
     *
     * @param $resource
     */
    protected function setResource($resource)
    {
        if (empty($resource)) {
            $resource = 'import_' . time();
        }
        $this->resource = $resource;
    }

    /**
     * Fetches CSV header
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function getHeader()
    {
        ini_set('auto_detect_line_endings', true);
        if (($handle = fopen($this->file, "r")) !== false) {
            $header = fgetcsv($handle, 0, ',');
            static::isHeader($header);

            return $header;
        } else {
            throw new InternalServerErrorException('Could not open uploaded CSV file.');
        }
    }

    /**
     * Checks CSV header
     *
     * @param $header
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected static function isHeader($header)
    {
        foreach ($header as $h) {
            if (is_numeric($h)) {
                throw new BadRequestException(
                    'First row in the uploaded CSV contains numeric value and cannot be used as field name. ' .
                    'Please make sure first row of your CSV file is the header row.'
                );
            }
        }

        return true;
    }

    /**
     * Creates DB table based on CSV header row
     *
     * @param $header
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function createDbTableFromHeader($header)
    {
        if (empty($this->resource)) {
            $this->resource = 'import_' . time();
        }
        if (!$this->resourceExists('_schema/' . $this->resource)) {
            $schema = $this->createSchemaFromHeader($header);
            $this->createTable($schema);

            return true;
        } else {
            throw new BadRequestException(
                'Importing CSV data into existing DB table [' . $this->resource . '] is not supported.'
            );
        }
    }

    /**
     * Creates table schema definition based on CSV header
     *
     * @param array $header
     *
     * @return array
     */
    protected function createSchemaFromHeader($header)
    {
        $schema = [
            'name'        => $this->resource,
            'label'       => ucfirst($this->resource),
            'description' => 'Table created from CSV data import',
            'plural'      => ucfirst(str_plural($this->resource)),
            'field'       => []
        ];

        foreach ($header as $h) {
            $schema['field'][] = [
                'name'       => $h,
                'type'       => 'string',
                'default'    => null,
                'required'   => false,
                'allow_null' => true
            ];
        }

        return $schema;
    }

    /**
     * Checks to see if target resource already exists or not
     *
     * @param $resource
     *
     * @return bool
     */
    protected function resourceExists($resource)
    {
        /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
        $rs = ServiceManager::handleRequest($this->service->getName(), Verbs::GET, $resource);
        if ($rs->getStatusCode() === HttpStatusCodes::HTTP_NOT_FOUND) {
            return false;
        }

        return true;
    }

    /**
     * Creates import table
     *
     * @param $schema
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function createTable($schema)
    {
        /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
        $rs = ServiceManager::handleRequest(
            $this->service->getName(),
            Verbs::POST,
            '_schema',
            [],
            [],
            ResourcesWrapper::wrapResources($schema)
        );

        if (in_array($rs->getStatusCode(), [HttpStatusCodes::HTTP_OK, HttpStatusCodes::HTTP_CREATED])) {
            return true;
        }

        $content = $rs->getContent();
        Log::error(
            'Failed to create table for importing CSV data: ' .
            (is_array($content) ? print_r($content, true) : $content)
        );
        throw new InternalServerErrorException('Failed to create table for importing CSV data. See log for details.');
    }

    /**
     * Imports CSV data
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function importData()
    {
        if (($handle = fopen($this->file, "r")) !== false) {
            $header = fgetcsv($handle, 0, ',');
            $result = [];
            while (false !== ($row = fgetcsv($handle))) {
                $new = [];
                foreach ($header as $key => $value) {
                    $new[$value] = array_get($row, $key);
                }
                $result[] = $new;
                if (count($result) === 500) {
                    $this->insertTableData($result);
                }
            }

            if (count($result) > 0) {
                $this->insertTableData($result, false);
            }

            fclose($handle);
            unlink($this->file);
        } else {
            throw new InternalServerErrorException('Could not open uploaded CSV file.');
        }
    }

    /**
     * Inserts data into table
     *
     * @param      $data
     * @param bool $useQueue
     */
    protected function insertTableData(& $data, $useQueue = true)
    {
        $job = new DBInsert($this->service->getName(), $this->resource, $data);
        if ($useQueue !== true) {
            $job->onConnection('sync');
        }

        dispatch($job);
        $data = [];
    }
}