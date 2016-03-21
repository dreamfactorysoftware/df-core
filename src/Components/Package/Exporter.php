<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;
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
        $this->exportData();
        $m = $this->generateManifest();

        return $this->data;
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

        foreach ($this->data as $service => $resources) {
            foreach ($resources as $resourceName => $records) {
                $names = [];
                foreach ($records as $i => $record) {
                    if (isset($record['name'])) {
                        $names[] = $record['name'];
                    } else {
                        $names[] = $requestedItems[$service][$resourceName][$i];
                    }
                }
                $manifest[$service][$resourceName] = $names;
            }
        }

        return $manifest;
    }

    public function exportData()
    {
        $items = $this->package->getItems();
        foreach ($items as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                if ($resourceName !== 'app_files') {
                    $this->data[$service][$resourceName] = $this->exportResource($service, $resourceName, $details);
                }
            }
        }
    }

    protected function exportResource($service, $resource, $details)
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
    protected function initExportZipFile()
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