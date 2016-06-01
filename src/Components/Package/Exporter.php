<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Services\BaseFileService;
use Illuminate\Support\Arr;
use ServiceManager;

/**
 * Class Exporter.
 * This class uses the Package instance and handles
 * everything to extract and export package file.
 *
 * @package DreamFactory\Core\Components\Package
 */
class Exporter
{
    /** Default storage for extracted package file. */
    const DEFAULT_STORAGE = 'files';

    /** Default storage folder for extracted package file. */
    const DEFAULT_STORAGE_FOLDER = '__EXPORTS';

    /** @type \DreamFactory\Core\Components\Package\Package */
    private $package;

    /**
     * Extracted data.
     *
     * @type array
     */
    protected $data = [];

    /**
     * Storage service id or name. This storage
     * service is used to store the extracted zip file.
     *
     * @type int|string
     */
    protected $storageService;

    /**
     * Storage folder name. This storage folder
     * is used to store the extracted zip file in.
     *
     * @type string
     */
    protected $storageFolder;

    /**
     * Default relations to extract for a resource.
     *
     * @type array
     */
    protected $defaultRelation = [
        'system/role'    => ['role_service_access_by_role_id', 'role_adldap_by_role_id'],
        'system/user'    => ['user_to_app_to_role_by_user_id'],
        'system/service' => ['service_doc_by_service_id']
    ];

    /**
     * Stores temp files to be deleted in __destruct.
     *
     * @type array
     */
    protected $destructible = [];

    /**
     * Exporter constructor.
     *
     * @param Package $package
     */
    public function __construct($package)
    {
        $this->package = $package;
        $this->storageService = $this->package->getExportStorageService(static::DEFAULT_STORAGE);
        $this->storageFolder = $this->package->getExportStorageFolder(static::DEFAULT_STORAGE_FOLDER);
    }

    /**
     * Cleans up all temp files.
     */
    public function __destruct()
    {
        foreach ($this->destructible as $d) {
            @unlink($d);
        }
    }

    /**
     * Extracts resources and exports the package file.
     * Returns URL of the exported file.
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
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

    /**
     * Checks to see if the URL of the exported zip file is
     * publicly accessible.
     *
     * @return bool
     */
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

    /**
     * Returns a manifest file for system-wide resources.
     *
     * @param bool $systemOnly
     *
     * @return array
     */
    public function getManifestOnly($systemOnly = false)
    {
        $this->data['system']['role'] = $this->getAllResources('system', 'role', ['fields' => 'id,name']);
        $this->data['system']['service'] = $this->getAllResources('system', 'service', ['fields' => 'id,name']);
        $this->data['system']['app'] = $this->getAllResources('system', 'app', ['fields' => 'id,name']);
        $this->data['system']['app_group'] = $this->getAllResources('system', 'app_group', ['fields' => 'id,name']);
        $this->data['system']['user'] = $this->getAllResources('system', 'user', ['fields' => 'id,email']);
        $this->data['system']['admin'] = $this->getAllResources('system', 'admin', ['fields' => 'id,email']);
        $this->data['system']['custom'] = $this->getAllResources('system', 'custom');
        $this->data['system']['cors'] = $this->getAllResources('system', 'cors', ['fields' => 'id,path']);
        $this->data['system']['email_template'] =
            $this->getAllResources('system', 'email_template', ['fields' => 'id,name']);
        $this->data['system']['event'] =
            $this->getAllResources('system', 'event', ['type' => 'process', 'only_scripted' => true]);
        $this->data['system']['lookup'] = $this->getAllResources('system', 'lookup', ['fields' => 'id,name']);

        $manifest = $this->package->getManifestHeader();
        foreach ($this->data as $service => $resource) {
            foreach ($resource as $resourceName => $records) {
                foreach ($records as $record) {
                    $api = $service . '/' . $resourceName;
                    switch ($api) {
                        case 'system/user':
                        case 'system/admin':
                            $manifest['service'][$service][$resourceName][] = array_get($record, 'email');
                            break;
                        case 'system/event':
                            // Do not use array_get here as system/event names have dot (.) in them.
                            $manifest['service'][$service][$resourceName][] = $record;
                            break;
                        case 'system/cors':
                            $manifest['service'][$service][$resourceName][] = array_get($record, 'id');
                            break;
                        default:
                            $manifest['service'][$service][$resourceName][] = array_get($record, 'name');
                            break;
                    }
                }
            }
        }

        if (false === $systemOnly) {
            foreach ($manifest['service']['system']['service'] as $service) {
                try {
                    if ($this->isServiceActive($service)) {
                        $group = static::getServiceGroup($service);
                        switch ($group) {
                            case ServiceTypeGroups::FILE:
                                $manifest['service'][$service] = $this->getAllResources(
                                    $service,
                                    '',
                                    ['as_list' => true, 'full_tree' => true]
                                );
                                break;
                            case ServiceTypeGroups::DATABASE:
                                $manifest['service'][$service]['_schema'] = $this->getAllResources(
                                    $service,
                                    '_schema',
                                    ['as_list' => true]
                                );
                                break;
                        }
                    } else {
                        \Log::warning('Excluding inactive service:' . $service . ' from manifest.');
                    }
                } catch (\Exception $e) {
                    // Error occurred. Log and let go.
                    \Log::alert('Failed to include service:' .
                        $service .
                        ' in manifest due to error:' .
                        $e->getMessage());
                }
            }
        }

        return $manifest;
    }

    /**
     * Checks to see if service exists and active.
     *
     * @param $serviceName
     *
     * @return bool
     */
    protected function isServiceActive($serviceName)
    {
        $service = Service::getCachedByName($serviceName);

        if (!empty($service)) {
            return boolval(array_get($service, 'is_active', false));
        }

        return false;
    }

    /**
     * Retrieves service group name.
     *
     * @param $serviceName
     *
     * @return null
     */
    protected static function getServiceGroup($serviceName)
    {
        $service = Service::getCachedByName($serviceName);
        if (!empty($service)) {

            return ServiceManager::getServiceType(array_get($service, 'type'))->getGroup();
        }

        return null;
    }

    /**
     * Returns all resources for service/resource.
     *
     * @param       $service
     * @param       $resource
     * @param array $params
     * @param null  $payload
     *
     * @return array|\DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \Exception
     */
    protected function getAllResources($service, $resource, $params = [], $payload = null)
    {
        $resources = $this->getResource($service, $resource, $params, $payload);

        if (Arr::isAssoc($resources)) {
            $resources = [$resources];
        }

        return $resources;
    }

    /**
     * Adds manifest file to the package.
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function addManifestFile()
    {
        $this->package->zipManifestFile($this->generateManifest());
    }

    /**
     * Adds resource files to the package.
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function addResourceFiles()
    {
        foreach ($this->data as $service => $resources) {
            foreach ($resources as $resourceName => $records) {
                $this->package->zipResourceFile($service . '/' . $resourceName . '.json', $records);
            }
        }
    }

    /**
     * Adds app files or other storage files to the package.
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function addStorageFiles()
    {
        $items = $this->package->getStorageServices();
        foreach ($items as $service => $resources) {
            if (is_string($resources)) {
                $resources = explode(',', $resources);
            }
            foreach ($resources as $resource) {
                /** @type BaseFileService $storage */
                $storage = ServiceManager::getService($service);
                if (!$storage) {
                    throw new InternalServerErrorException("Can not find storage service $service.");
                }

                $container = $storage->getContainerId();
                if ($storage->driver()->folderExists($container, $resource)) {
                    $zippedResource = $this->getStorageFolderZip($storage, $resource);
                    if ($zippedResource !== false) {
                        $newFileName = $service . '/' . rtrim($resource, '/') . '/' . md5($resource) . '.zip';
                        $this->package->zipFile($zippedResource, $newFileName);
                        $this->destructible[] = $zippedResource;
                    }
                } elseif ($storage->driver()->fileExists($container, $resource)) {
                    $content = $storage->driver()->getFileContent($container, $resource, null, false);
                    $this->package->zipContent($service . '/' . $resource, $content);
                }
            }
        }
    }

    /**
     * Returns the path of the zip file containing
     * app files or other storage files.
     *
     * @param BaseFileService $storage
     * @param                 $resource
     *
     * @return bool|string
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function getStorageFolderZip($storage, $resource)
    {
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

    /**
     * Generates the package manifest.
     *
     * @return array
     */
    protected function generateManifest()
    {
        $manifest = $this->package->getManifestHeader();

        $requestedItems = $this->package->getServices();

        foreach ($requestedItems as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                if (isset($this->data[$service][$resourceName])) {
                    $records = $this->data[$service][$resourceName];
                    foreach ($records as $i => $record) {
                        $api = $service . '/' . $resourceName;
                        switch ($api) {
                            case 'system/user':
                            case 'system/admin':
                                $manifest['service'][$service][$resourceName][] =
                                    array_get($record, 'email', array_get($record, 'id', array_get($details, $i, [])));
                                break;
                            default:
                                $manifest['service'][$service][$resourceName][] =
                                    array_get($record, 'name', array_get($record, 'id', array_get($details, $i, [])));
                                break;
                        }
                    }
                } else {
                    $manifest['service'][$service][$resourceName] = $details;
                }
            }
        }

        return $manifest;
    }

    /**
     * Extracts all non-storage resources for export.
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function gatherData()
    {
        try {
            $items = $this->package->getNonStorageServices();
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

    /**
     * Extracts a specific service/resource for export.
     *
     * @param string $service  service name
     * @param string $resource resource name
     * @param mixed  $details  resource details
     *
     * @return array|\DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     * @throws \Exception
     */
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
            } elseif (!empty($filter)) {
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
                } elseif (is_array($related)) {
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

    /**
     * Returns the full resource path for extracting a resource.
     *
     * @param string $service
     * @param string $resource
     * @param mixed  $id
     * @param array  $params
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    protected function getResourcePath($service, $resource, $id, array &$params)
    {
        $api = $service . '/' . $resource;
        switch ($api) {
            case $service . '/_table':
            case $service . '/_proc':
            case $service . '/_func':
                throw new NotImplementedException('Exporting ' . $resource . ' resource is not supported.');
                break;
            case $service . '/_schema':
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
                } elseif (is_string($id)) {
                    if (in_array($api, ['system/user', 'system/admin'])) {
                        array_set($params, 'filter', 'email="' . $id . '"');
                    } else {
                        array_set($params, 'filter', 'name="' . $id . '"');
                    }
                }
        }

        return $resource;
    }

    /**
     * Sets the default relations to extract for some resources.
     *
     * @param string $service
     * @param string $resource
     * @param array  $params
     */
    protected function setDefaultRelations($service, $resource, &$params)
    {
        $api = $service . '/' . $resource;
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

    /**
     * Extracts a resource
     *
     * @param string $service
     * @param string $resource
     * @param array  $params
     * @param null   $payload
     *
     * @return array|\DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \Exception
     */
    protected function getResource($service, $resource, $params = [], $payload = null)
    {
        try {
            $result = ServiceManager::handleRequest($service, Verbs::GET, $resource, $params, [], $payload);

            // Handle responses from system/custom and user/custom APIs
            $rSeg = explode('/', $resource);
            $api = $service . '/' . $rSeg[0];
            if (isset($rSeg[1]) && in_array($api, ['system/custom', 'user/custom'])) {
                $result = ['name' => $rSeg[1], 'value' => $result];
            }
        } catch (NotFoundException $e) {
            throw new NotFoundException('Resource not found for ' . $service . '/' . $resource);
        } catch (\Exception $e) {
            throw $e;
        }

        if (is_string($result)) {
            $result = ['value' => $result];
        } elseif (Arr::isAssoc($result) &&
            config('df.always_wrap_resources') === true &&
            isset($result[config('df.resources_wrapper')])
        ) {
            $result = $result[config('df.resources_wrapper')];
        }

        if (in_array($api, ['system/user', 'system/admin'])) {
            $this->setUserPassword($result);
        }

        return $result;
    }

    /**
     * Sets user password encrypted when package is secured with a password.
     *
     * @param array $users
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function setUserPassword(array & $users)
    {
        if (!empty($users)) {
            if (Arr::isAssoc($users)) {
                $users = [$users];
            }

            foreach ($users as $i => $user) {
                $model = User::find($user['id']);
                if (!empty($model)) {
                    $users[$i]['password'] = $model->password;
                }
            }
        }
    }
}