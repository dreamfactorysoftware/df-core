<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Enums\Verbs;

class Importer
{
    protected $package = null;
    
    public function __construct($package)
    {
        $this->package = new Package($package);
    }

    public function import()
    {
        $this->insertRole();
        $this->insertService();
        $this->insertRoleServiceAccess();
    }

    protected function insertRole()
    {
        try {
            $roles = $this->package->getResourceFromZip('system/role.json');

            foreach ($roles as $i => $role) {
                unset($role['id']);
                unset($role['role_service_access_by_role_id']);
                unset($role['last_modified_by_id']);
                $role['created_by_id'] = Session::getCurrentUserId();
                $roles[$i] = $role;
            }

            $payload = ResourcesWrapper::wrapResources($roles);
            ServiceHandler::handleRequest(Verbs::POST, 'system', 'role', [], $payload);

            return true;
        } catch (\Exception $e){
            throw new InternalServerErrorException('Failed to insert roles. '.$e->getMessage());
        }
    }

    protected function insertService()
    {
        try {
            $services = $this->package->getResourceFromZip('system/service.json');

            foreach ($services as $i => $service) {
                unset($service['id']);
                unset($service['last_modified_by_id']);
                $service['created_by_id'] = Session::getCurrentUserId();
                if(!empty(array_get($service, 'config.default_role'))){
                    $newRoleId = $this->getNewRoleId(array_get($service, 'config.default_role'));
                    if(!empty($newRoleId)){
                        $service['config']['default_role'] = $newRoleId;
                    } else {
                        // If no new role found then do not store config. default_role field is not nullable.
                        unset($service['config']);
                    }
                }
                $services[$i] = $service;
            }

            $payload = ResourcesWrapper::wrapResources($services);
            ServiceHandler::handleRequest(Verbs::POST, 'system', 'service', [], $payload);

            return true;
        } catch(\Exception $e){
            throw new InternalServerErrorException('Failed to insert services. '.$e->getMessage());
        }
    }

    protected function insertRoleServiceAccess()
    {
        try {
            $rolesInZip = $this->package->getResourceFromZip('system/role.json');

            foreach ($rolesInZip as $riz) {
                $rsa = $riz['role_service_access_by_role_id'];
                $role = Role::whereName($riz['name'])->first();
                $newRoleId = $role->id;
                if(!empty($role) && !empty($rsa)) {
                    foreach ($rsa as $i => $r) {
                        $r['role_id'] = $newRoleId;
                        $r['service_id'] = $this->getNewServiceId($r['service_id']);
                        $r['created_by_id'] = Session::getCurrentUserId();
                        unset($r['id']);
                        unset($r['last_modified_by_id']);
                        $rsa[$i] = $r;
                    }

                    $roleUpdate = ['role_service_access_by_role_id' => $rsa];
                    ServiceHandler::handleRequest(Verbs::PATCH, 'system', 'role/' . $newRoleId, [], $roleUpdate);
                }
            }

            return true;
        } catch(\Exception $e){
            throw new InternalServerErrorException('Failed to insert role service access records for roles. '.$e->getMessage());
        }
    }

    protected function getNewRoleId($oldRoleId)
    {
        $roles = $this->package->getResourceFromZip('system/role.json');
        $roleName = null;
        foreach ($roles as $role){
            if($oldRoleId === $role['id']){
                $roleName = $role['name'];
                break;
            }
        }

        if(!empty($roleName)){
            $newRole = Role::whereName($roleName)->first(['id']);
            if(!empty($newRole)){
                return $newRole->id;
            }
        }

        return null;
    }

    protected function getNewServiceId($oldServiceId)
    {
        $services = $this->package->getResourceFromZip('system/service.json');
        $serviceName = null;
        foreach ($services as $service){
            if($oldServiceId === $service['id']){
                $serviceName = $service['name'];
                break;
            }
        }

        if(!empty($serviceName)) {
            $newService = Service::whereName($serviceName)->first(['id']);
            if (!empty($newService)) {
                return $newService->id;
            }
        }

        return null;
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