<?php
namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\Package as Packager;

class Package extends BaseSystemResource
{
    protected function handleGET()
    {
        $manifest = $this->request->getPayloadData();

        $package = new Packager($manifest);

        return $package->export();

    }

    protected function handlePOST()
    {

    }
}