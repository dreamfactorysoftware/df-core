<?php

namespace DreamFactory\Core\Components\Package;

class Importer
{
    protected $package = null;
    
    public function __construct($package)
    {
        $this->package = new Package($package);
    }

    public function import()
    {

    }

    protected function insertRole()
    {
        $roles = $this->package->getResourceFromZip('system/role.json');
    }

    protected function insertService($service, $resource)
    {

    }

    protected function insertRoleServiceAccess($service, $resource)
    {

    }

    protected function insertApp($service, $resource)
    {

    }

    protected function insertOtherResource($service, $resource)
    {

    }

    protected function storeFiles()
    {

    }
}