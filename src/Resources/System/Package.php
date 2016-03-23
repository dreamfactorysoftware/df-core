<?php
namespace DreamFactory\Core\Resources\System;


use DreamFactory\Core\Components\Package\Exporter;

class Package extends BaseSystemResource
{
    protected function handlePOST()
    {
        $manifest = $this->request->getPayloadData();
        $package = new Exporter($manifest);
        $url = $package->export();
        $public = $package->isPublic();

        return ['path' => $url, 'is_public' => $public];
    }
}