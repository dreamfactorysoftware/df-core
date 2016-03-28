<?php
namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\Package\Exporter;
use DreamFactory\Core\Components\Package\Importer;

class Package extends BaseSystemResource
{
    /** @inheritdoc */
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
            $password = $this->request->input('password');
            $package = new \DreamFactory\Core\Components\Package\Package($file);
            $package->setPassword($password);
            $importer = new Importer($package, true);
            $importer->import();
            $log = $importer->getLog();

            return ['success' => true, 'log' => $log];
        } else {
            //Export
            $manifest = $this->request->getPayloadData();
            $package = new \DreamFactory\Core\Components\Package\Package($manifest);
            $exporter = new Exporter($package);
            $url = $exporter->export();
            $public = $exporter->isPublic();

            return ['success' => true, 'path' => $url, 'is_public' => $public];
        }
    }
}