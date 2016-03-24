<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Services\BaseFileService;
use Illuminate\Support\Arr;

class Exporter
{
    const DEFAULT_STORAGE = 'files';

    const DEFAULT_STORAGE_FOLDER = '__EXPORTS';

    /** @type \DreamFactory\Core\Components\Package\Package */
    private $package;

    /**
     * Extracted data.
     *
     * @type array
     */
    protected $data = [];

    protected $storageService;

    protected $storageFolder;

    protected $defaultRelation = [
        'system/role' => ['role_service_access_by_role_id']
    ];

    public function __construct(array $manifest)
    {
        $this->package = new Package($manifest);
        $this->storageService = $this->getStorageService($manifest);
        $this->storageFolder = $this->getStorageFolder($manifest);
    }

    public function export()
    {
        $this->gatherData();
        $this->package->initZipFile();
        $this->addManifestFile();
        $this->addResourceFiles();
        $this->addStorageFiles();
        $url = $this->package->saveZipFile($this->storageService, $this->storageFolder);

        return $url;
    }

    public function isPublic()
    {
        $service = Service::whereName($this->storageService)->first()->toArray();
        $publicPaths = array_get($service, 'config.public_path');

        if (!empty($publicPaths)) {
            foreach ($publicPaths as $pp) {
                if (trim($this->storageFolder, '/') == trim($pp, '/')) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getStorageService($manifest)
    {
        $storage = array_get($manifest, 'storage', static::DEFAULT_STORAGE);
        if (is_array($storage)) {
            $name = array_get($storage, 'name', array_get($storage, 'id', static::DEFAULT_STORAGE));
            if (is_numeric($name)) {
                $service = Service::find($name);
                return $service->name;
            }
            return $name;
        }
        return $storage;
    }

    protected function getStorageFolder($manifest)
    {
        $folder = static::DEFAULT_STORAGE_FOLDER;
        $storage = array_get($manifest, 'storage', null);
        if (is_array($storage)) {
            $folder = array_get($storage, 'folder', static::DEFAULT_STORAGE_FOLDER);
        }
        return $folder;
    }

    protected function addManifestFile()
    {
        $this->package->zipManifestFile($this->generateManifest());
    }

    protected function addResourceFiles()
    {
        foreach ($this->data as $service => $resources) {
            foreach ($resources as $resourceName => $records) {
                $this->package->zipResourceFile($service . '/' . $resourceName . '.json', $records);
            }
        }
    }

    protected function addStorageFiles()
    {
        $items = $this->package->getStorageItems();
        foreach ($items as $service => $resources) {
            if (is_string($resources)) {
                $resources = explode(',', $resources);
            }
            foreach ($resources as $resource) {
                $zippedResource = $this->getStorageZip($service, $resource);
                if ($zippedResource !== false) {
                    $newFileName = $service . '/' . str_replace('/', '_', rtrim($resource, '/')) . '.zip';
                    $this->package->zipFile($zippedResource, $newFileName);
                }
            }
        }
    }

    protected function getStorageZip($service, $resource)
    {
        /** @type BaseFileService $storage */
        $storage = ServiceHandler::getService($service);
        if (!$storage) {
            throw new InternalServerErrorException("Can not find storage service $service.");
        }

        $resource = rtrim($resource, '/') . DIRECTORY_SEPARATOR;
        $zip = new \ZipArchive();
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $zipFileName = $tmpDir . str_replace('/', '_', $resource) . time() . '.zip';

        if (true !== $zip->open($zipFileName, \ZipArchive::CREATE)) {
            throw new InternalServerErrorException('Could not create zip file for extracting ' . $resource);
        }

        $container = $storage->getContainerId();
        if ($storage->driver()->folderExists($container, $resource)) {
            $storage->driver()->getFolderAsZip($container, $resource, $zip, $zipFileName, true);
        } else {
            return false;
        }

        $zip->close();

        return $zipFileName;
    }

    protected function generateManifest()
    {
        $manifest = $this->package->getManifestHeader();

        $requestedItems = $this->package->getItems();

        foreach ($requestedItems as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                if (isset($this->data[$service][$resourceName])) {
                    $records = $this->data[$service][$resourceName];
                    foreach ($records as $i => $record) {
                        $manifest[$service][$resourceName][] = array_get($record, 'name', array_get($details, $i, []));
                    }
                } else {
                    $manifest[$service][$resourceName] = $details;
                }
            }
        }

        return $manifest;
    }

    public function gatherData()
    {
        try {
            $items = $this->package->getNonStorageItems();
            foreach ($items as $service => $resources) {
                foreach ($resources as $resourceName => $details) {
                    $this->data[$service][$resourceName] = $this->gatherResource($service, $resourceName, $details);
                }
            }
        } catch (NotFoundException $e) {
            throw $e;
        } catch (BadRequestException $e) {
            throw $e;
        } catch (NotImplementedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException('Failed to export resources. ' . $e->getMessage());
        }
    }

    protected function gatherResource($service, $resource, $details)
    {
        $export = [];
        $params = [];
        if (Arr::isAssoc($details)) {
            $ids = array_get($details, 'ids');
            $filter = array_get($details, 'filter');
            $related = array_get($details, 'related');
            if (!empty($ids)) {
                if (is_array($ids)) {
                    $params['ids'] = implode(',', $ids);
                } else {
                    $params['ids'] = $ids;
                }
            } else if (!empty($filter)) {
                $params['filter'] = $filter;
            } else {
                throw new BadRequestException('No resource ids or filter provided for ' .
                    $service .
                    '/' .
                    $resource .
                    '.');
            }

            if (!empty($related)) {
                if (is_string($related)) {
                    $params['related'] = $related;
                } else if (is_array($related)) {
                    $params['related'] = implode(',', $related);
                }
            }

            $this->setDefaultRelations($service, $resource, $params);
            $export = $this->getResource($service, $resource, $params);
        } else {
            foreach ($details as $id) {
                $this->setDefaultRelations($service, $resource, $params);
                $resourcePath = $this->getResourcePath($service, $resource, $id, $params);
                $result = $this->getResource($service, $resourcePath, $params);
                if (!Arr::isAssoc($result)) {
                    $export = array_merge($export, $result);
                } else {
                    $export[] = $result;
                }
            }
        }

        return $export;
    }

    protected function getResourcePath($service, $resource, $id, array &$params)
    {
        $api = strtolower($service . '/' . $resource);
        switch ($api) {
            case $service . '/_table':
                throw new NotImplementedException('Exporting _table resource is not supported.');
                break;
            case $service . '/_schema':
            case $service . '/_proc':
            case $service . '/_func':
            case 'system/event':
            case 'system/custom':
            case 'user/custom':
                if (is_string($id)) {
                    $resource .= '/' . $id;
                } else {
                    throw new BadRequestException('Granular export not supported for resource ' . $resource);
                }
                break;
            default:
                if (is_numeric($id)) {
                    $resource .= '/' . $id;
                } else if (is_string($id)) {
                    array_set($params, 'filter', 'name="' . $id . '"');
                }
        }

        return $resource;
    }

    protected function setDefaultRelations($service, $resource, &$params)
    {
        $api = strtolower($service . '/' . $resource);
        $relations = array_get($this->defaultRelation, $api);
        if (!empty($relations)) {
            if (!isset($params['related'])) {
                $params['related'] = implode(',', $relations);
            } else {
                foreach ($relations as $relation) {
                    if (strpos($params['related'], $relation) === false) {
                        $params['related'] .= ',' . $relation;
                    }
                }
            }
        }
    }

    protected function getResource($service, $resource, $params = [], $payload = null)
    {
        try {
            $result = ServiceHandler::handleRequest(Verbs::GET, $service, $resource, $params, $payload);

            // Handle responses from system/custom and user/custom APIs
            $rSeg = explode('/', $resource);
            $api = $service . '/' . $rSeg[0];
            if (in_array($api, ['system/custom', 'user/custom'])) {
                $result = ['name' => $rSeg[1], 'value' => $result];
            }
        } catch (NotFoundException $e) {
            throw new NotFoundException('Resource not found for ' . $service . '/' . $resource);
        } catch (\Exception $e) {
            throw $e;
        }

        if (is_string($result)) {
            return ['value' => $result];
        } else if (Arr::isAssoc($result) &&
            config('df.always_wrap_resources') === true &&
            isset($result[config('df.resources_wrapper')])
        ) {
            return $result[config('df.resources_wrapper')];
        } else {
            return $result;
        }
    }
}