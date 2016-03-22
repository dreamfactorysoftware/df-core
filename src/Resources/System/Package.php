<?php
namespace DreamFactory\Core\Resources\System;


use DreamFactory\Core\Components\Package\Exporter;

class Package extends BaseSystemResource
{
    protected function handleGET()
    {
        $manifest = json_decode($this->request->getParameter('manifest'), JSON_UNESCAPED_SLASHES);

        $package = new Exporter($manifest);

        return $package->export();

    }

    protected function handlePOST()
    {

    }
}