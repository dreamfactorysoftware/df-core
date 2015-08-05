<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseModel;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseFileService;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;

class Packager
{
    /**
     * Package file extension.
     */
    const FILE_EXTENSION = 'dfpkg';

    /**
     * Default container for app files.
     */
    const DEFAULT_CONTAINER = 'applications';

    /**
     * Resource wrapper from config.
     *
     * @type string
     */
    protected $resourceWrapper = null;

    /**
     * Package zip file.
     *
     * @type \ZipArchive
     */
    protected $zip = null;

    /**
     * @param mixed $fileInfo
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function __construct($fileInfo = null)
    {
        if (is_array($fileInfo)) {
            $this->verifyUploadedFile($fileInfo);
        } else if (!empty($fileInfo)) {
            $this->verifyImportFromUrl($fileInfo);
        }
        $this->resourceWrapper = \Config::get('df.resources_wrapper');
    }

    /**
     * Deletes the temp uploaded file and closes the Zip archive
     */
    public function __destruct()
    {
        @unlink($this->zip->filename);
        $this->zip->close();
    }

    /**
     * @param $file
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function verifyUploadedFile($file)
    {
        if (is_array($file['error'])) {
            throw new BadRequestException("Only a single application package file is allowed for import.");
        }

        if (UPLOAD_ERR_OK !== ($error = $file['error'])) {
            throw new InternalServerErrorException(
                "Failed to receive upload of '" . $file['name'] . "': " . $error
            );
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (static::FILE_EXTENSION != $extension) {
            throw new BadRequestException("Only package files ending with '" .
                static::FILE_EXTENSION .
                "' are allowed for import.");
        }

        $this->setZipFile($file['tmp_name']);
    }

    /**
     * @param $url
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function verifyImportFromUrl($url)
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        if (static::FILE_EXTENSION != $extension) {
            throw new BadRequestException("Only package files ending with '" .
                static::FILE_EXTENSION .
                "' are allowed for import.");
        }

        try {
            // need to download and extract zip file and move contents to storage
            $file = FileUtilities::importUrlFileToTemp($url);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to import package $url.\n{$ex->getMessage()}");
        }

        $this->setZipFile($file);
    }

    /**
     * @param string $file
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function setZipFile($file)
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($file)) {
            throw new InternalServerErrorException('Error opening zip file.');
        }

        $this->zip = $zip;
    }

    /**
     * @return integer|null
     */
    private function getDefaultStorageServiceId()
    {
        /** @type BaseModel $model */
        $model = Service::whereType('local_file')->first();
        $storageServiceId = ($model) ? $model->{Service::getPrimaryKeyStatic()} : null;

        return $storageServiceId;
    }

    /**
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    private function getAppInfo()
    {
        $this->zip;
        $data = ($this->zip->getFromName('description.json')) ?: $this->zip->getFromName('app.json');
        $this->zip->deleteName('description.json');
        $this->zip->deleteName('app.json');

        if (false === $data) {
            throw new BadRequestException('No application description file in this package file.');
        } else {
            $data = DataFormatter::jsonToArray($data);
            $data['name'] = ArrayUtils::get($data, 'api_name', ArrayUtils::get($data, 'name'));
        }

        return $data;
    }

    /**
     * @param $record
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function insertAppRecord($record)
    {
        try {
            $result = ServiceHandler::handleRequest(Verbs::POST, 'system', 'app', ['fields' => '*'], [$record]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Could not create the application.\n{$ex->getMessage()}");
        }

        return (isset($result[$this->resourceWrapper])) ? $result[$this->resourceWrapper][0] : $result[0];
    }

    /**
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function insertServices()
    {
        $data = $this->zip->getFromName('services.json');
        $this->zip->deleteName('services.json');

        if (false !== $data) {
            $data = DataFormatter::jsonToArray($data);
            try {
                Service::create($data);
            } catch (\Exception $ex) {
                throw new InternalServerErrorException("Could not create the services.\n{$ex->getMessage()}");
            }

            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function insertSchemas()
    {
        $data = $this->zip->getFromName('schema.json');
        $this->zip->deleteName('schema.json');
        if (false !== $data) {
            $data = DataFormatter::jsonToArray($data);
            $services = ArrayUtils::get($data, 'service');
            if (!empty($services)) {
                foreach ($services as $schemas) {
                    $serviceName = ArrayUtils::get($schemas, 'name');
                    $tables = ArrayUtils::get($schemas, 'table');
                    if (!empty($tables)) {
                        $result = ServiceHandler::handleRequest(
                            Verbs::POST,
                            $serviceName,
                            '_schema',
                            [],
                            [$this->resourceWrapper => $tables]
                        );

                        if (isset($result[0]['error'])) {
                            $msg = $result[0]['error']['message'];
                            throw new InternalServerErrorException("Could not create the database tables for this application.\n$msg");
                        }
                    }
                }
            } else {
                throw new BadRequestException("Could not create the database tables for this application.\nDatabase service or schema not found in schema.json.");
            }

            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function insertData()
    {
        $data = $this->zip->getFromName('data.json');
        $this->zip->deleteName('data.json');

        if (false !== $data) {
            $data = DataFormatter::jsonToArray($data);
            $services = ArrayUtils::get($data, 'service');
            if (!empty($services)) {
                foreach ($services as $service) {
                    $serviceName = ArrayUtils::get($service, 'name');
                    $tables = ArrayUtils::get($service, 'table');

                    foreach ($tables as $table) {
                        $tableName = ArrayUtils::get($table, 'name');
                        $records = ArrayUtils::get($table, 'record');

                        $result = ServiceHandler::handleRequest(
                            Verbs::POST,
                            $serviceName,
                            '_table/' . $tableName,
                            [],
                            [$this->resourceWrapper => $records]
                        );

                        if (isset($result['record'][0]['error'])) {
                            $msg = $result['record'][0]['error']['message'];
                            throw new InternalServerErrorException("Could not insert the database entries for table '$tableName'' for this application.\n$msg");
                        }
                    }
                }
            } else {
                throw new BadRequestException("Could not create the database tables for this application.\nDatabase service or data not found.");
            }

            return true;
        }

        return false;
    }

    /**
     * @param array $appInfo
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    private function storeApplicationFiles($appInfo)
    {
        $appName = ArrayUtils::get($appInfo, 'name');
        $storageServiceId = ArrayUtils::get($appInfo, 'storage_service_id', $this->getDefaultStorageServiceId());
        $container = ArrayUtils::get($appInfo, 'storage_container', static::DEFAULT_CONTAINER);

        /** @var $service BaseFileService */
        $service = ServiceHandler::getServiceById($storageServiceId);
        if (empty($service)) {
            throw new InternalServerErrorException(
                "App record created, but failed to import files due to unknown storage service with id '$storageServiceId'."
            );
        }

        if (empty($container)) {
            $info = $service->extractZipFile($appName, '', $this->zip, false, $appName . '/');
        } else {
            $info = $service->extractZipFile($container, '', $this->zip);
        }

        return $info;
    }

    /**
     * @param null $record
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \Exception
     */
    public function importAppFromPackage($record = null)
    {
        $record = ArrayUtils::clean($record);
        $data = $this->getAppInfo();

        // merge in overriding parameters from request if given
        $record = array_merge($data, $record);

        \DB::beginTransaction();
        $appResults = $this->insertAppRecord($record);

        try {
            $this->insertServices();
            $this->insertSchemas();
            $this->insertData();
            $this->storeApplicationFiles($record);
        } catch (\Exception $ex) {
            //Rollback all db changes;
            \DB::rollBack();

            throw $ex;
        }

        \DB::commit();

        return $appResults;
    }
}