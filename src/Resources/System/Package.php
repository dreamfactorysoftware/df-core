<?php
namespace DreamFactory\Core\Resources\System;


use DreamFactory\Core\Components\Package\Exporter;

class Package extends BaseSystemResource
{
    protected function handleGET()
    {
        $manifest = $this->request->getPayloadData();

        $package = new Exporter($manifest);

        return $package->export();

    }

    protected function handlePOST()
    {

    }
}