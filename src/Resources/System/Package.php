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
            $importer = new Importer($file);
            $importer->import();

            return ['success' => true, 'log'=>$importer->getLog()];
        } else {
            $manifest = $this->request->getPayloadData();
            $exporter = new Exporter($manifest);
            $url = $exporter->export();
            $public = $exporter->isPublic();

            return ['path' => $url, 'is_public' => $public];
        }
    }
}