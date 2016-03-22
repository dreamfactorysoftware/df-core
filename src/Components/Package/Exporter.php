<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Services\BaseFileService;
use Illuminate\Support\Arr;

class Exporter
{
    private $package = null;

    /**
     * Package zip file.
     *
     * @type \ZipArchive
     */
    protected $zip = null;

    /**
     * Zip file full path.
     *
     * @type string
     */
    protected $zipFilePath = null;

    /**
     * Extracted data.
     *
     * @type array
     */
    protected $data = [];

    protected $defaultRelation = [
        'system/role' => ['role_service_access_by_role_id']
    ];

    public function __construct(array $manifest)
    {
        $this->package = new Package($manifest);
    }

    public function export()
    {
        $this->gatherData();
        //return $this->data;
        $this->initZipFile();
        $this->zipManifestFile();
        $this->zipResourceFiles();
        $this->zipAppFiles();
        $this->zip->close();

        FileUtilities::sendFile($this->zipFilePath);

        return null;
    }

    protected function zipManifestFile()
    {
        $manifest = $this->generateManifest();
        if (!$this->zip->addFromString('package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES))) {
            throw new InternalServerErrorException("Failed to add manifest file.");
        }
    }
    
    protected function zipResourceFiles()
    {
        foreach($this->data as $service => $resources){
            foreach($resources as $resourceName => $records){
                if(!$this->zip->addFromString($service.DIRECTORY_SEPARATOR.$resourceName.'.json', json_encode($records, JSON_UNESCAPED_SLASHES))){
                    throw new InternalServerErrorException("Failed to add ".$service.DIRECTORY_SEPARATOR.$resourceName.'.json');
                }
            }
        }
    }

    protected function zipAppFiles()
    {
        $items = $this->package->getItems();
        if(!empty(array_get($items, 'system.app_files'))){
            $appFiles = array_get($items, 'system.app_files');
            $apps = array_get($this->data, 'system.app');
            foreach($appFiles as $appFile){
                foreach($apps as $app){
                    if(is_string($appFile) && $appFile === array_get($app, 'name')){
                        break;
                    } else if(is_int($appFile) && $appFile === array_get($app, 'id')){
                        break;
                    }
                }
                $appName = array_get($app, 'name');
                $storageServiceId = array_get($app, 'storage_service_id');
                $storageFolder = array_get($app, 'storage_container');

                if (empty($storageServiceId)) {
                    throw new InternalServerErrorException("Can not find storage service identifier for $appFile.");
                }

                /** @type BaseFileService $storage */
                $storage = ServiceHandler::getServiceById($storageServiceId);
                if (!$storage) {
                    throw new InternalServerErrorException("Can not find storage service by identifier '$storageServiceId''.");
                }

                if (empty($storageFolder)) {
                    if ($storage->driver()->containerExists($appName)) {
                        $storage->driver()->getFolderAsZip($appName, '', $this->zip, $this->zipFilePath, true);
                    }
                } else {
                    if ($storage->driver()->folderExists($storageFolder, $appName)) {
                        $storage->driver()->getFolderAsZip($storageFolder, $appName, $this->zip, $this->zipFilePath, true);
                    }
                }
            }
        }
    }

    protected function generateManifest()
    {
        $manifest = [
            'version'      => Package::VERSION,
            'df_version'   => config('df.version'),
            'secured'      => $this->package->isSecured(),
            'description'  => '',
            'created_date' => $this->package->getCreatedDate()
        ];

        $requestedItems = $this->package->getItems();

        foreach($requestedItems as $service => $resources){
            foreach($resources as $resourceName => $details){
                if(isset($this->data[$service][$resourceName])){
                    $records = $this->data[$service][$resourceName];
                    foreach($records as $i => $record){
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
        $items = $this->package->getItems();
        foreach ($items as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                if ($resourceName !== 'app_files') {
                    $this->data[$service][$resourceName] = $this->gatherResource($service, $resourceName, $details);
                }
            }
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
        } catch (NotFoundException $e) {
            throw new NotFoundException('Record not found for ' . $service . '/' . $resource);
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

    /**
     * Initialize export zip file.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function initZipFile()
    {
        $host = php_uname('n');
        $filename = $host . '_' . date('Y-m-d_H:i:s', time());
        $zip = new \ZipArchive();
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $zipFileName = $tmpDir . $filename . '.zip';
        $this->zip = $zip;
        $this->zipFilePath = $zipFileName;

        if (true !== $this->zip->open($zipFileName, \ZipArchive::CREATE)) {
            throw new InternalServerErrorException('Can not create package file.');
        }

        return true;
    }
}