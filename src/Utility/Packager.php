<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Enums\AppTypes;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Contracts\FileServiceInterface;
use DreamFactory\Core\Enums\Verbs;
use ServiceManager;

class Packager
{
    /**
     * Package file extension.
     */
    const FILE_EXTENSION = 'dfpkg';

    /**
     * Default app URL
     */
    const DEFAULT_URL = 'index.html';

    /**
     * Resource wrapper from config.
     *
     * @type string
     */
    protected $resourceWrapper = null;

    /**
     * @type bool|mixed
     */
    protected $resourceWrapped = true;

    /**
     * Package zip file.
     *
     * @type \ZipArchive
     */
    protected $zip = null;

    /**
     * @type string zip file full path
     */
    protected $zipFilePath = null;

    /**
     * App ID of the app to export.
     *
     * @type int
     */
    protected $exportAppId = 0;

    /**
     * Services to export.
     *
     * @type array
     */
    protected $exportServices = [];

    /**
     * Schemas to export.
     *
     * @type array
     */
    protected $exportSchemas = [];

    /**
     * @param mixed $fileInfo
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function __construct($fileInfo = null)
    {
        if (is_numeric($fileInfo)) {
            $this->exportAppId = $fileInfo;
        } elseif (is_array($fileInfo)) {
            $this->verifyUploadedFile($fileInfo);
        } elseif (!empty($fileInfo) && is_string($fileInfo)) {
            $this->verifyImportFromUrl($fileInfo);
        }

        $this->resourceWrapped = \Config::get('df.always_wrap_resources');
        $this->resourceWrapper = \Config::get('df.resources_wrapper');
    }

    /**
     * Deletes the temp uploaded file and closes the Zip archive
     */
    public function __destruct()
    {
        if (!empty($this->zip)) {
            $this->zip->close();
        }
        if (file_exists($this->zipFilePath)) {
            @unlink($this->zipFilePath);
        }
    }

    /**
     * Sets services and schemas to export.
     *
     * @param $services
     * @param $schemas
     */
    public function setExportItems($services, $schemas)
    {
        $this->exportServices = $services;
        $this->exportSchemas = $schemas;
    }

    /**
     * Verifies the uploaed file for importing process.
     *
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
     * Verifies file import from url.
     *
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
            throw new InternalServerErrorException("Failed to import package from $url. {$ex->getMessage()}");
        }

        $this->setZipFile($file);
    }

    /**
     * Opens and sets the zip file for import.
     *
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
        $this->zipFilePath = $file;
    }

    /**
     * @return integer|null
     */
    private function getDefaultStorageServiceId()
    {
        if (!empty($result = ServiceManager::getServiceListByType('local_file', ['id']))) {
            return array_get(current($result), 'id');
        }

        return null;
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
            $data['name'] = array_get($data, 'api_name', array_get($data, 'name'));
        }

        return $data;
    }

    /**
     * Sanitizes the app record description.json
     *
     * @param $record
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    private function sanitizeAppRecord(& $record)
    {
        if (!is_array($record)) {
            throw new BadRequestException('Invalid App data provided');
        }

        if (!isset($record['name'])) {
            throw new BadRequestException('No App name provided in description.json');
        }

        if (!isset($record['type'])) {
            $record['type'] = AppTypes::NONE;
        }

        if (isset($record['active']) && !isset($record['is_active'])) {
            $record['is_active'] = $record['active'];
            unset($record['active']);
        } elseif (!isset($record['is_active'])) {
            $record['is_active'] = true;
        }

        if ($record['type'] === AppTypes::STORAGE_SERVICE) {
            if (!empty($serviceId = array_get($record, 'storage_service_id'))) {
                $fileServiceNames = ServiceManager::getServiceNamesByGroup(ServiceTypeGroups::FILE);
                $serviceName = ServiceManager::getServiceNameById($serviceId);
                if (!in_array($serviceName, $fileServiceNames)) {
                    throw new BadRequestException('Invalid Storage Service provided.');
                }
            } else {
                $record['storage_service_id'] = $this->getDefaultStorageServiceId();
            }

            if (!empty(array_get($record, 'storage_container'))) {
                $record['storage_container'] = trim($record['storage_container'], '/');
            } else {
                $record['storage_container'] = camelize($record['name']);
            }
        } else {
            $record['storage_service_id'] = null;
            $record['storage_container'] = null;
        }

        if (!isset($record['url'])) {
            $record['url'] = static::DEFAULT_URL;
        } else {
            $record['url'] = ltrim($record['url'], '/');
        }

        if (isset($record['path'])) {
            $record['path'] = ltrim($record['path'], '/');
        }

        if ($record['type'] === AppTypes::STORAGE_SERVICE || $record['type'] === AppTypes::PATH) {
            if (empty(array_get($record, 'path'))) {
                throw new BadRequestException('No Application Path provided in description.json');
            }
        } elseif ($record['type'] === AppTypes::URL) {
            if (empty(array_get($record, 'url'))) {
                throw new BadRequestException('No Application URL provided in description.json');
            }
        }
    }

    /**
     * @param      $record
     * @param null $ssId
     * @param null $sc
     *
     * @return mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function insertAppRecord(& $record, $ssId = null, $sc = null)
    {
        $record['storage_service_id'] = $ssId;
        $record['storage_container'] = $sc;
        $this->sanitizeAppRecord($record);

        try {
            $result = ServiceManager::handleRequest('system', Verbs::POST, 'app', ['fields' => '*'], [], [$record]);
            if ($result->getStatusCode() >= 300) {
                throw ResponseFactory::createExceptionFromResponse($result);
            }
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Could not create the application.\n{$ex->getMessage()}");
        }

        $result = $result->getContent();

        return ($this->resourceWrapped) ? $result[$this->resourceWrapper][0] : $result[0];
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
                foreach ($data as $service) {
                    Service::create($service);
                }
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
     * @throws \Exception
     */
    private function insertSchemas()
    {
        $data = $this->zip->getFromName('schema.json');
        $this->zip->deleteName('schema.json');
        if (false !== $data) {
            $data = DataFormatter::jsonToArray($data);
            $services = array_get($data, 'service');
            if (!empty($services)) {
                foreach ($services as $schemas) {
                    $serviceName = array_get($schemas, 'name');
                    $tables = array_get($schemas, 'table');
                    $resource = ($this->resourceWrapped) ? [$this->resourceWrapper => $tables] : [$tables];
                    if (!empty($tables)) {
                        try {
                            $result = ServiceManager::handleRequest(
                                $serviceName,
                                Verbs::POST,
                                '_schema',
                                [],
                                [],
                                $resource
                            );
                            if ($result->getStatusCode() >= 300) {
                                throw ResponseFactory::createExceptionFromResponse($result);
                            }
                        } catch (\Exception $e) {
                            if (in_array($e->getCode(), [404, 500])) {
                                throw $e;
                            } else {
                                \Log::alert('Failed to create schema. ' . $e->getMessage());
                            }
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
     * @throws \Exception
     */
    private function insertData()
    {
        $data = $this->zip->getFromName('data.json');
        $this->zip->deleteName('data.json');

        if (false !== $data) {
            $data = DataFormatter::jsonToArray($data);
            $services = array_get($data, 'service');
            if (!empty($services)) {
                foreach ($services as $service) {
                    $serviceName = array_get($service, 'name');
                    $tables = array_get($service, 'table');

                    foreach ($tables as $table) {
                        $tableName = array_get($table, 'name');
                        $records = array_get($table, 'record');
                        $resource = ($this->resourceWrapped) ? [$this->resourceWrapper => $records] : [$records];
                        try {
                            $result = ServiceManager::handleRequest(
                                $serviceName,
                                Verbs::POST,
                                '_table/' . $tableName,
                                [],
                                [],
                                $resource
                            );
                            if ($result->getStatusCode() >= 300) {
                                throw ResponseFactory::createExceptionFromResponse($result);
                            }
                        } catch (\Exception $e) {
                            if (in_array($e->getCode(), [404, 500])) {
                                throw $e;
                            } else {
                                \Log::alert('Failed to insert data. ' . $e->getMessage());
                            }
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
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    private function storeApplicationFiles($appInfo)
    {
        if (array_get($appInfo, 'type', AppTypes::NONE) === AppTypes::STORAGE_SERVICE) {
            $appName = camelize(array_get($appInfo, 'name'));
            $storageServiceId = array_get($appInfo, 'storage_service_id', $this->getDefaultStorageServiceId());
            $storageFolder = array_get($appInfo, 'storage_container', $appName);

            /** @var $service FileServiceInterface */
            $service = ServiceManager::getServiceById($storageServiceId);
            if (empty($service)) {
                throw new InternalServerErrorException(
                    "App record created, but failed to import files due to unknown storage service with id '$storageServiceId'."
                );
            } elseif (!($service instanceof FileServiceInterface)) {
                throw new InternalServerErrorException(
                    "App record created, but failed to import files due to storage service with id '$storageServiceId' not being a file service."
                );
            }
            $info = $service->extractZipFile($storageFolder, $this->zip);

            return $info;
        } else {
            return [];
        }
    }

    /**
     * @param null | integer $storageServiceId
     * @param null | string  $storageContainer
     * @param null | array   $record
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \Exception
     */
    public function importAppFromPackage($storageServiceId = null, $storageContainer = null, $record = null)
    {
        $record = (array)$record;
        $data = $this->getAppInfo();

        // merge in overriding parameters from request if given
        $record = array_merge($data, $record);

        \DB::beginTransaction();
        $appResults = $this->insertAppRecord($record, $storageServiceId, $storageContainer);

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

    /**
     * Initialize export zip file.
     *
     * @param $appName
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function initExportZipFile($appName)
    {
        $zip = new \ZipArchive();
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $zipFileName = $tmpDir . $appName . '.' . static::FILE_EXTENSION;
        $this->zip = $zip;
        $this->zipFilePath = $zipFileName;

        if (true !== $this->zip->open($zipFileName, \ZipArchive::CREATE)) {
            throw new InternalServerErrorException('Can not create package file for this application.');
        }

        return true;
    }

    /**
     * Package app info for export.
     *
     * @param $app
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function packageAppDescription($app)
    {
        $record = [
            'name'                    => $app->name,
            'description'             => $app->description,
            'is_active'               => $app->is_active,
            'type'                    => $app->type,
            'path'                    => $app->path,
            'url'                     => $app->url,
            'requires_fullscreen'     => $app->requires_fullscreen,
            'allow_fullscreen_toggle' => $app->allow_fullscreen_toggle,
            'toggle_location'         => $app->toggle_location
        ];

        if (!$this->zip->addFromString('description.json', json_encode($record, JSON_UNESCAPED_SLASHES))) {
            throw new InternalServerErrorException("Can not include description in package file.");
        }

        return true;
    }

    /**
     * Package app files for export.
     *
     * @param $app
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    private function packageAppFiles($app)
    {
        $appName = $app->name;
        $zipFileName = $this->zipFilePath;
        $storageServiceId = $app->storage_service_id;
        $storageFolder = $app->storage_container;

        if (empty($storageServiceId)) {
            $storageServiceId = $this->getDefaultStorageServiceId();
        }

        if (empty($storageServiceId)) {
            throw new InternalServerErrorException("Can not find storage service identifier.");
        }

        /** @type FileServiceInterface $storage */
        $storage = ServiceManager::getServiceById($storageServiceId);
        if (!$storage) {
            throw new InternalServerErrorException("Can not find storage service by identifier '$storageServiceId''.");
        }

        if ($storage->folderExists($storageFolder)) {
            $storage->getFolderAsZip($storageFolder, $this->zip, $zipFileName, true);
        }

        return true;
    }

    /**
     * Package services for export.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function packageServices()
    {
        if (!empty($this->exportServices)) {
            $services = [];

            foreach ($this->exportServices as $serviceName) {
                if (is_numeric($serviceName)) {
                    /** @type Service $service */
                    $service = Service::find($serviceName);
                } else {
                    /** @type Service $service */
                    $service = Service::whereName($serviceName)->whereDeletable(1)->first();
                }

                if (!empty($service)) {
                    $services[] = [
                        'name'        => $service->name,
                        'label'       => $service->label,
                        'description' => $service->description,
                        'type'        => $service->type,
                        'is_active'   => $service->is_active,
                        'mutable'     => $service->mutable,
                        'deletable'   => $service->deletable,
                        'config'      => $service->config
                    ];
                }
            }

            if (!empty($services) &&
                !$this->zip->addFromString('services.json', json_encode($services, JSON_UNESCAPED_SLASHES))
            ) {
                throw new InternalServerErrorException("Can not include services in package file.");
            }

            return true;
        }

        return false;
    }

    /**
     * Package schemas for export.
     * @return bool
     * @throws InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    private function packageSchemas()
    {
        if (!empty($this->exportSchemas)) {
            $schemas = [];

            foreach ($this->exportSchemas as $serviceName => $component) {
                if (is_array($component)) {
                    $component = implode(',', $component);
                }

                if (is_numeric($serviceName)) {
                    /** @type Service $service */
                    $service = Service::find($serviceName);
                } else {
                    /** @type Service $service */
                    $service = Service::whereName($serviceName)->whereDeletable(1)->first();
                }

                if (!empty($service) && !empty($component)) {
                    if ($service->type === 'sql_db') {
                        $result = ServiceManager::handleRequest(
                            $serviceName,
                            Verbs::GET,
                            '_schema',
                            ['ids' => $component]
                        );
                        if ($result->getStatusCode() >= 300) {
                            throw ResponseFactory::createExceptionFromResponse($result);
                        }

                        $schema = $result->getContent();
                        $schemas[] = [
                            'name'  => $serviceName,
                            'table' => ($this->resourceWrapped) ? $schema[$this->resourceWrapper] : $schema
                        ];
                    }
                }
            }

            if (!empty($schemas) &&
                !$this->zip->addFromString('schema.json', json_encode(['service' => $schemas], JSON_UNESCAPED_SLASHES))
            ) {
                throw new InternalServerErrorException("Can not include database schema in package file.");
            }

            return true;
        }

        return false;
    }

    /**
     * Package data for export.
     */
    private function packageData()
    {
        //Todo: We need to load data unfiltered.
    }

    /**
     * @param bool|true  $includeFiles
     * @param bool|false $includeData
     *
     * @return null
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \Exception
     */
    public function exportAppAsPackage($includeFiles = true, $includeData = false)
    {
        /** @type App $app */
        $app = App::find($this->exportAppId);

        if (empty($app)) {
            throw new NotFoundException('App not found in database with app id - ' . $this->exportAppId);
        }

        $appName = $app->name;

        try {
            $this->initExportZipFile($appName);
            $this->packageAppDescription($app);
            $this->packageServices();
            $this->packageSchemas();

            if ($includeData) {
                $this->packageData();
            }

            if ($app->type === AppTypes::STORAGE_SERVICE && $includeFiles) {
                $this->packageAppFiles($app);
            }

            $this->zip->close();
            FileUtilities::sendFile($this->zipFilePath, true);

            return null;
        } catch (\Exception $e) {
            //Do necessary things here.

            throw $e;
        }
    }
}