<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\App as AppModel;
use DreamFactory\Core\Utility\Packager;

class App extends BaseSystemResource
{
    protected function handleGET()
    {
        if(!empty($this->resource)){
            if($this->request->getParameterAsBool('pkg')){
                $includeFiles = $this->request->getParameterAsBool('include_files');
                $includeServices = $this->request->getParameterAsBool('include_services');
                $includeSchema = $this->request->getParameterAsBool('include_schema');
                $includeData = $this->request->getParameterAsBool('include_data');
                $services = explode(',', $this->request->getParameter('services'));

                $package = new Packager($this->resource);

//                $services = [
//                    [
//                        'name' => 'mysql',
//                        'component' => 'Events,todo'
//                    ],
//                    [
//                        'name' => 'dfdev'
//                    ],
//                    [
//                        'name' => 'email'
//                    ],
//                    [
//                        'name' => 'system'
//                    ],
//                    [
//                        'name' => 's3'
//                    ]
//                ];

                $package->setExportItems($services);
                return $package->exportAppAsPackage($includeFiles, $includeServices, $includeSchema, $includeData);
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
        $importUrl = $this->request->getParameter('import_url');

        if(!empty($uploadedFiles)){
            $package = new Packager($uploadedFiles);
            $results = $package->importAppFromPackage();
        }  elseif (!empty($importUrl)) {
            $package = new Packager($importUrl);
            $results = $package->importAppFromPackage();
        } else {
            $results = parent::handlePOST();
        }

        return $results;
    }
}