<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Facades\ServiceManager;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Jobs\DBInsert;
use DreamFactory\Library\Utility\Inflector;
use Log;

class ResourceImporter
{
    const FILE_EXTENSION = ['csv'];

    /**
     * Temporary file path
     *
     * @var null|string
     */
    protected $file = null;

    /**
     * @var \DreamFactory\Core\Components\RestHandler
     */
    protected $service;

    protected $resource = null;

    protected $extension = null;

    public function __construct($file, $service, $resource = null)
    {
        if (is_array($file)) {
            $this->file = $this->verifyUploadedFile($file);
        } elseif (is_string($file)) {
            $this->file = $this->verifyImportFromUrl($file);
        } else {
            throw new BadRequestException('Invalid or no file supplied for import.');
        }
        $this->service = ServiceManager::getService($service);
        $this->resource = $resource;
    }

    public function import()
    {
        /** @var \DreamFactory\Core\Contracts\ServiceTypeInterface $type */
        $serviceTypeGroup = $this->service->getServiceTypeInfo()->getGroup();

        if (ServiceTypeGroups::DATABASE === $serviceTypeGroup) {
            if ($this->extension === 'csv') {
                $header = $this->getCSVHeader();
                if ($this->createDbTableFromHeader($header)) {
                    try {
                        $this->importCSVData();

                        return true;
                    } catch (\Exception $e) {
                        $this->deleteTable();

                        throw $e;
                    }
                }

                return false;
            }

            // This is not reachable anyway.
            return false;
        } else {
            throw new NotImplementedException(
                'Import feature does not support service type group ' . $serviceTypeGroup . '. ' .
                'Only Database services are currently supported.'
            );
        }
    }

    public function getResourceName()
    {
        return $this->resource;
    }

    /**
     * Verifies the uploaed file for importing process.
     *
     * @param array $file
     *
     * @return string
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function verifyUploadedFile(array $file)
    {
        if (is_array($file['error'])) {
            throw new BadRequestException("Only a single file is allowed for import.");
        }

        if (UPLOAD_ERR_OK !== ($error = $file['error'])) {
            throw new InternalServerErrorException(
                "Failed to upload '" . $file['name'] . "': " . $error
            );
        }

        $this->checkFileExtension($file['name']);

        return $file['tmp_name'];
    }

    /**
     * Verifies file import from url.
     *
     * @param $url
     *
     * @return string
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function verifyImportFromUrl($url)
    {
        $this->checkFileExtension($url);
        try {
            // need to download and extract zip file and move contents to storage
            $file = FileUtilities::importUrlFileToTemp($url);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to import file from $url. {$ex->getMessage()}");
        }

        return $file;
    }

    protected function checkFileExtension($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, static::FILE_EXTENSION)) {
            throw new BadRequestException(
                "Unsupported file type. Supported types are " . implode(', ', static::FILE_EXTENSION)
            );
        }
        $this->extension = $extension;
    }

    protected function readFile()
    {
        $contents = file_get_contents($this->file);

        return $contents;
    }

    protected function getCSVHeader()
    {
        ini_set('auto_detect_line_endings', true);
        if (($handle = fopen($this->file, "r")) !== false) {
            $header = fgetcsv($handle, 0, ',');
            static::validateCSVHeader($header);

            return $header;
        } else {
            throw new InternalServerErrorException('Could not open uploaded CSV file.');
        }
    }

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

    protected static function validateCSVHeader($header)
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

    protected function createSchemaFromHeader($header)
    {
        $schema = [
            'name'        => $this->resource,
            'label'       => ucfirst($this->resource),
            'description' => 'Table created from CSV data import',
            'plural'      => ucfirst(Inflector::pluralize($this->resource)),
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

    protected function resourceExists($resource)
    {
        /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
        $rs = ServiceManager::handleRequest($this->service->getName(), Verbs::GET, $resource);
        if ($rs->getStatusCode() === HttpStatusCodes::HTTP_NOT_FOUND) {
            return false;
        }

        return true;
    }

    protected function createTable($schema)
    {
        /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
        $rs = ServiceManager::handleRequest($this->service->getName(), Verbs::POST, '_schema', [], [], [
            'resource' => [$schema]
        ]);

        if (in_array($rs->getStatusCode(), [HttpStatusCodes::HTTP_OK, HttpStatusCodes::HTTP_CREATED])) {
            return true;
        }

        $content = $rs->getContent();
        Log::error('Failed to create table for importing CSV data: ' .
            (is_array($content) ? print_r($content, true) : $content));
        throw new InternalServerErrorException('Failed to create table for importing CSV data. See log for details.');
    }

    protected function deleteTable()
    {
        if ($this->resourceExists('_schema/' . $this->resource)) {
            /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
            $rs = ServiceManager::handleRequest($this->service->getName(), Verbs::DELETE, '_schema/' . $this->resource);
            if ($rs->getStatusCode() === HttpStatusCodes::HTTP_OK) {
                return true;
            }
            $content = $rs->getContent();
            Log::error('Failed to delete table on import failure: ' .
                (is_array($content) ? print_r($content, true) : $content));
            throw new InternalServerErrorException('Failed to delete table on import failure. See log for details.');
        }
    }

    protected function importCSVData()
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

    protected function insertTableData(& $data, $useQueue = true)
    {
        if($useQueue === true) {
            $job = new DBInsert($this->service->getName(), $this->resource, $data);
            dispatch($job);
            $data = [];
        } else {
            /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
            $rs = ServiceManager::handleRequest(
                $this->service->getName(),
                Verbs::POST, '_table/' . $this->resource,
                [],
                [],
                ['resource' => $data]
            );
            if (in_array($rs->getStatusCode(), [HttpStatusCodes::HTTP_OK, HttpStatusCodes::HTTP_CREATED])) {
                $data = [];
            } else {
                $content = $rs->getContent();
                Log::error('Failed to insert data into table: ' .
                    (is_array($content) ? print_r($content, true) : $content));
                throw new InternalServerErrorException('Failed to insert data into table. See log for details.');
            }
        }
    }
}