<?php
namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\Package\Exporter;
use DreamFactory\Core\Components\Package\Importer;

class Package extends BaseSystemResource
{
    protected function handlePOST()
    {
        // Get uploaded file
        $file = $this->request->getFile('files');

        // Get file from a url
        if (empty($file)) {
            $file = $this->request->getPayloadData('import_url');
        }

        if (!empty($file)) {
            //Import
            $importer = new Importer($file);
            $importer->import();
            $log = $importer->getLog();

            return ['success' => true, 'log' => $log];
        } else {
            //Export
            $manifest = $this->request->getPayloadData();
            $exporter = new Exporter($manifest);
            $url = $exporter->export();
            $public = $exporter->isPublic();

            return ['success' => true, 'path' => $url, 'is_public' => $public];
        }
    }
}