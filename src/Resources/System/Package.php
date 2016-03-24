<?php
namespace DreamFactory\Core\Resources\System;


use DreamFactory\Core\Components\Package\Exporter;
use DreamFactory\Core\Components\Package\Importer;

class Package extends BaseSystemResource
{
    protected function handlePOST()
    {
        $file = $this->request->getFile('files');
        if(empty($file)) {
            $file = $this->request->getPayloadData('import_url');
        }

        if(!empty($file)){
            $package = new Importer($file);
        } else {
            $manifest = $this->request->getPayloadData();
            $package = new Exporter($manifest);
            $url = $package->export();
            $public = $package->isPublic();

            return ['path' => $url, 'is_public' => $public];
        }
    }
}