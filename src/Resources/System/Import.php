<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\ResourceImporter;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Http\Controllers\StatusController;

class Import extends BaseSystemResource
{

    protected function handleGET()
    {
        return false;
    }

    protected function handlePOST()
    {
        // Get uploaded file
        $file = $this->request->getFile('file', $this->request->getFile('files'));
        $service = $this->request->input('service', 'db');
        $resource = $this->request->input('resource');

        if(empty($file)){
            $file = $this->request->input('import_url');
        }

        if(!empty($file)){
            $importer = new ResourceImporter($file, $service, $resource);
            if($importer->import()){
                $importedResource = $importer->getResourceName();
                return [
                    'resource' => StatusController::getURI($_SERVER) .
                        '/api/v2/' .
                        $service .
                        '/_table/' .
                        $importedResource
                ];
            }

        } else {
            throw new BadRequestException(
                'No import file supplied. ' .
                'Please upload a file or provide an URL of a file to import. ' .
                'Supported files are ' . implode(', ', ResourceImporter::FILE_EXTENSION) . '.'
            );
        }
    }

}