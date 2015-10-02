<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\App as AppModel;
use DreamFactory\Core\Utility\Packager;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Services\BaseFileService;

class App extends BaseSystemResource
{
    /**
     * Handles GET action
     *
     * @return array|null
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     * @throws \Exception
     */
    protected function handleGET()
    {
        if (!empty($this->resource)) {
            /**
             * Example request:
             *
             * GET http://df.instance.com/api/v2/system/app/9?pkg=true&service=1,2,3&schema={"service":["table1","table2"]}&include_files=true
             *
             * GET http://df.instance.com/api/v2/system/app/9?pkg=true&service=s1,s2,s3&schema={"1":["table1","table2"]}&include_files=true
             */
            if ($this->request->getParameterAsBool('pkg')) {
                $schemaString = $this->request->getParameter('schema');
                $schemas = (!empty($schemaString)) ? json_decode($schemaString, true) : [];
                $serviceString = $this->request->getParameter('service');
                $services = (!empty($serviceString)) ? explode(',', $serviceString) : [];
                $includeFiles = $this->request->getParameterAsBool('include_files');
                $includeData = $this->request->getParameterAsBool('include_data');
                $package = new Packager($this->resource);
                $package->setExportItems($services, $schemas);

                return $package->exportAppAsPackage($includeFiles, $includeData);
            }
        }

        return parent::handleGET();
    }

    /**
     * Handles PATCH action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        if (!empty($this->resource)) {
            if ($this->request->getParameterAsBool(ApiOptions::REGENERATE)) {
                /** @var AppModel $appClass */
                $appClass = $this->model;
                $app = $appClass::find($this->resource)->first();
                $app->api_key = $appClass::generateApiKey($app->name);
                $app->save();
            }
        }

        return parent::handlePATCH();
    }

    /**
     * Handles POST action
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface|\DreamFactory\Core\Utility\ServiceResponse|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    protected function handlePOST()
    {
        $uploadedFiles = $this->request->getFile('file');
        $importUrl = $this->request->getPayloadData('import_url');
        $storageServiceId = $this->request->input('storage_service_id');
        $storageContainer = $this->request->input('storage_container');

        if (!empty($uploadedFiles)) {
            $package = new Packager($uploadedFiles);
            $results = $package->importAppFromPackage($storageServiceId, $storageContainer);
        } elseif (!empty($importUrl)) {
            $package = new Packager($importUrl);
            $results = $package->importAppFromPackage($storageServiceId, $storageContainer);
        } else {
            $results = parent::handlePOST();
        }

        return $results;
    }

    /**
     * Handles DELETE action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handleDELETE()
    {
        $deleteStorage = $this->request->getParameterAsBool('delete_storage');
        $fields = $this->request->getParameter('fields');

        if ($deleteStorage) {
            if (empty($fields)) {
                $fields = 'id,storage_service_id,storage_container';
            } else if ($fields !== '*') {
                $fields = explode(',', $fields);

                if (!in_array('id', $fields)) {
                    $fields[] = 'id';
                }
                if (!in_array('storage_service_id', $fields)) {
                    $fields[] = 'storage_service_id';
                }
                if (!in_array('storage_container', $fields)) {
                    $fields[] = 'storage_container';
                }

                $fields = implode(',', $fields);
            }
            $this->request->setParameter('fields', $fields);
        }

        $result = parent::handleDELETE();

        if ($deleteStorage) {
            $temp = $result;
            $wrapper = ResourcesWrapper::getWrapper();
            if (isset($result[$wrapper])) {
                $temp = ResourcesWrapper::unwrapResources($temp);
            }

            if (ArrayUtils::isArrayNumeric($temp)) {
                foreach ($temp as $app) {
                    static::deleteHostedAppStorage(
                        $app['id'],
                        $app['storage_service_id'],
                        $app['storage_container']
                    );
                }
            } else {
                static::deleteHostedAppStorage(
                    $temp['id'],
                    $temp['storage_service_id'],
                    $temp['storage_container']
                );
            }
        }

        return $result;
    }

    /**
     * Deletes hosted app files from storage.
     *
     * @param $id
     * @param $storageServiceId
     * @param $storageFolder
     */
    protected static function deleteHostedAppStorage($id, $storageServiceId, $storageFolder)
    {
        $app = AppModel::whereId($id)->first();

        if (empty($app) && !empty($storageServiceId) && !empty($storageFolder)) {
            /** @type BaseFileService $storageService */
            $storageService = ServiceHandler::getServiceById($storageServiceId);

            if ($storageService->driver()->folderExists(null, $storageFolder)) {
                $storageService->driver()->deleteFolder(null, $storageFolder, true);
            }
        }
    }
}