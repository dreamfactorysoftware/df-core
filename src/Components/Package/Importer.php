<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseFileService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Enums\Verbs;

class Importer
{
    protected $package = null;

    protected $log = [];

    protected $ignoreExisting = true;

    public function __construct($package, $ignoreExisting = true)
    {
        $this->package = new Package($package);
        $this->ignoreExisting = $ignoreExisting;
    }

    public function import()
    {
        \DB::beginTransaction();

        try {
            $this->insertRole();
            $this->insertService();
            $this->insertRoleServiceAccess();
            $this->insertApp();
            $this->insertOtherResource();
            $this->insertEventScripts();
            $this->storeFiles();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }

        \DB::commit();
    }

    public function getLog()
    {
        return $this->log;
    }

    protected function insertRole()
    {
        $data = $this->package->getResourceFromZip('system/role.json');
        $roles = $this->cleanDuplicates($data, 'system', 'role');

        if (!empty($roles)) {
            try {
                foreach ($roles as $i => $role) {
                    $this->fixCommonFields($role);
                    unset($role['role_service_access_by_role_id']);
                    $roles[$i] = $role;
                }

                $payload = ResourcesWrapper::wrapResources($roles);
                ServiceHandler::handleRequest(Verbs::POST, 'system', 'role', [], $payload);

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert roles. ' . $e->getMessage());
            }
        } else {
            return false;
        }
    }

    protected function insertService()
    {
        $data = $this->package->getResourceFromZip('system/service.json');
        $services = $this->cleanDuplicates($data, 'system', 'service');

        if (!empty($services)) {
            try {
                foreach ($services as $i => $service) {
                    unset($service['id']);
                    unset($service['last_modified_by_id']);
                    $service['created_by_id'] = Session::getCurrentUserId();
                    if (!empty(array_get($service, 'config.default_role'))) {
                        $oldRoleId = array_get($service, 'config.default_role');
                        $newRoleId = $this->getNewRoleId($oldRoleId);
                        if (!empty($newRoleId)) {
                            array_set($service, 'config.default_role', $newRoleId);
                        } else {
                            // If no new role found then do not store config. default_role field is not nullable.
                            $this->log(
                                'warning',
                                'Skipping config for service ' .
                                $service['name'] .
                                '. Default role not found by role id ' .
                                $oldRoleId
                            );
                            \Log::debug('Skipped service.', $service);
                            unset($service['config']);
                        }
                    }
                    $services[$i] = $service;
                }

                $payload = ResourcesWrapper::wrapResources($services);
                ServiceHandler::handleRequest(Verbs::POST, 'system', 'service', [], $payload);

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert services. ' . $e->getMessage());
            }
        } else {
            return false;
        }
    }

    protected function insertRoleServiceAccess()
    {
        $rolesInZip = $this->package->getResourceFromZip('system/role.json');

        if (!empty($rolesInZip)) {
            try {
                foreach ($rolesInZip as $riz) {
                    $rsa = $riz['role_service_access_by_role_id'];
                    $role = Role::whereName($riz['name'])->first();
                    $newRoleId = $role->id;
                    if (!empty($role) && !empty($rsa)) {
                        foreach ($rsa as $i => $r) {
                            $this->fixCommonFields($r);
                            $newServiceId = $this->getNewServiceId($r['service_id']);

                            if (empty($newServiceId) && !empty($r['service_id'])) {
                                $this->log(
                                    'warning',
                                    'Skipping service_id for Role Service Access of Role. ' .
                                    $riz['name'] .
                                    '. Service not found for ' .
                                    $r['service_id']
                                );
                            }

                            $r['service_id'] = $newServiceId;
                            $rsa[$i] = $r;
                        }

                        $roleUpdate = ['role_service_access_by_role_id' => $rsa];
                        ServiceHandler::handleRequest(Verbs::PATCH, 'system', 'role/' . $newRoleId, [], $roleUpdate);
                    } else {
                        if (!empty($rsa) && empty($role)) {
                            $this->log(
                                'warning',
                                'Skipping Role Service Access for ' . $riz['name'] . ' No imported role found.'
                            );
                            \Log::debug('Skipped Role Service Access.', $riz);
                        } else {
                            $this->log('notice', 'No Role Service Access for role ' . $riz['name']);
                        }
                    }
                }

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert role service access records for roles. ' .
                    $e->getMessage());
            }
        } else {
            return false;
        }
    }

    protected function insertApp()
    {
        $data = $this->package->getResourceFromZip('system/app.json');
        $apps = $this->cleanDuplicates($data, 'system', 'app');

        if (!empty($apps)) {
            try {
                foreach ($apps as $i => $app) {
                    $this->fixCommonFields($app);
                    $this->unsetImportedRelations($app);
                    $newStorageId = $this->getNewServiceId($app['storage_service_id']);
                    $newRoleId = $this->getNewRoleId($app['role_id']);

                    if (empty($newStorageId) && !empty($app['storage_service_id'])) {
                        $this->log(
                            'warning',
                            'Skipping storage_service_id for app ' .
                            $app['name'] .
                            '. Service not found for ' .
                            $app['storage_service_id']
                        );
                    }

                    if (empty($newRoleId) && !empty($app['role_id'])) {
                        $this->log(
                            'warning',
                            'Skipping role_id for app ' .
                            $app['name'] .
                            '. Role not found for ' .
                            $app['role_id']
                        );
                    }

                    $app['storage_service_id'] = $newStorageId;
                    $app['role_id'] = $newRoleId;
                    $apps[$i] = $app;
                }

                $payload = ResourcesWrapper::wrapResources($apps);
                ServiceHandler::handleRequest(Verbs::POST, 'system', 'app', [], $payload);

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert apps. ' . $e->getMessage());
            }
        } else {
            return false;
        }
    }

    protected function insertOtherResource()
    {
        $items = $this->package->getNonStorageItems();

        foreach ($items as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                $api = $service . '/' . $resourceName;
                switch ($api) {
                    case 'system/app':
                    case 'system/role':
                    case 'system/service':
                    case 'system/event':
                        // Skip; already imported at this point.
                        break;
                    case $service . '/_table':
                    case $service . '/_proc':
                    case $service . '/_func':
                        // Not supported at this time.
                        $this->log('warning', 'Skipping resource ' . $resourceName . '. Not supported.');
                        break;
                    default:
                        $this->insertGenericResources($service, $resourceName);
                        break;
                }
            }
        }
    }

    protected function insertEventScripts()
    {
        $data = $this->package->getResourceFromZip('system/event.json');
        $scripts = $this->cleanDuplicates($data, 'system', 'event');

        if (!empty($scripts)) {
            try {
                foreach ($scripts as $script) {
                    $name = array_get($script, 'name');
                    $this->fixCommonFields($script);
                    ServiceHandler::handleRequest(Verbs::POST, 'system', 'event/' . $name, [], $script);
                }

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert event script. ' . $e->getMessage());
            }
        } else {
            return false;
        }
    }

    protected function insertGenericResources($service, $resource)
    {
        $data = $this->package->getResourceFromZip($service . '/' . $resource . '.json');
        $records = $this->cleanDuplicates($data, $service, $resource);

        if (!empty($records)) {
            try {
                foreach ($records as $i => $record) {
                    $this->fixCommonFields($record);
                    $this->unsetImportedRelations($record);
                    $records[$i] = $record;
                }

                $payload = ResourcesWrapper::wrapResources($records);
                ServiceHandler::handleRequest(Verbs::POST, $service, $resource, ['continue' => true], $payload);

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert ' .
                    $service .
                    '/' .
                    $resource .
                    '. ' .
                    $e->getMessage());
            }
        } else {
            return false;
        }
    }

    protected function storeFiles()
    {
        $items = $this->package->getStorageItems();

        foreach ($items as $service => $resources) {
            if (is_string($resources)) {
                $resources = explode(',', $resources);
            }

            try {
                /** @type BaseFileService $storage */
                $storage = ServiceHandler::getService($service);
                foreach ($resources as $resource) {
                    $resourcePath = $service . '/' . trim($resource, '/') . '/' . md5($resource) . '.zip';
                    $zip = $this->package->getZipFromZip($resourcePath);
                    if (!empty($zip)) {
                        $storage->driver()->extractZipFile(
                            $storage->getContainerId(),
                            rtrim($resource, '/') . '/',
                            $zip,
                            false,
                            rtrim($resource, '/') . '/'
                        );
                    }
                }
            } catch (\Exception $e){
                $this->log('error', 'Failed to store files for service '.$service.'. '.$e->getMessage());
            }
        }
    }

    protected function getNewRoleId($oldRoleId)
    {
        if (empty($oldRoleId)) {
            return null;
        }

        $roles = $this->package->getResourceFromZip('system/role.json');
        $roleName = null;
        foreach ($roles as $role) {
            if ($oldRoleId === $role['id']) {
                $roleName = $role['name'];
                break;
            }
        }

        if (!empty($roleName)) {
            $newRole = Role::whereName($roleName)->first(['id']);
            if (!empty($newRole)) {
                return $newRole->id;
            }
        }

        return null;
    }

    protected function getNewServiceId($oldServiceId)
    {
        if (empty($oldServiceId)) {
            return null;
        }

        $services = $this->package->getResourceFromZip('system/service.json');
        $serviceName = null;
        foreach ($services as $service) {
            if ($oldServiceId === $service['id']) {
                $serviceName = $service['name'];
                break;
            }
        }

        if (!empty($serviceName)) {
            $newService = Service::whereName($serviceName)->first(['id']);
            if (!empty($newService)) {
                return $newService->id;
            }
        }

        return null;
    }

    /**
     * Store log in importer and write to system log.
     *
     * @param string $level
     * @param string $msg
     * @param array  $context
     */
    protected function log($level, $msg, $context = [])
    {
        $this->log[$level][] = $msg;
        \Log::log($level, $msg, $context);
    }

    /**
     * Fix some common fields to make record ready for
     * inserting into db table.
     *
     * @param array $record
     */
    protected function fixCommonFields(array & $record)
    {
        unset($record['id']);
        unset($record['last_modified_by_id']);
        $record['created_by_id'] = Session::getCurrentUserId();
    }

    /**
     * Unset relations from record that are already imported
     * such as Role, Service, Role_Service_Access.
     *
     * @param array $record
     */
    protected function unsetImportedRelations(array & $record)
    {
        foreach ($record as $key => $value) {
            if (strpos($key, 'role_by_') !== false ||
                strpos($key, 'service_by_') !== false ||
                strpos($key, 'role_service_access_by_') !== false
            ) {
                if (empty($value) || is_array($value)) {
                    unset($record[$key]);
                }
            }
        }
    }

    protected function cleanDuplicates($data, $service, $resource)
    {
        $cleaned = [];
        $api = $service . '/' . $resource;

        switch ($api) {
            case 'system/admin':
            case 'system/user':
                $key = 'email';
                break;
            default:
                $key = 'name';
        }

        foreach ($data as $rec) {
            if (!$this->isDuplicate($service, $resource, array_get($rec, $key), $key)) {
                $cleaned[] = $rec;
            } else {
                $this->log(
                    'notice',
                    'Ignored duplicate found for ' .
                    $service . '/' . $resource .
                    ' with ' . $key .
                    ' ' . array_get($rec, $key)
                );
            }
        }

        return $cleaned;
    }

    protected function isDuplicate($service, $resource, $value, $key = 'name')
    {
        if ($this->ignoreExisting) {
            $api = $service . '/' . $resource;
            switch ($api) {
                case 'system/role':
                    $role = Role::where($key, $value)->first();

                    return (!empty($role)) ? true : false;
                case 'system/service':
                    $service = Service::where($key, $value)->first();

                    return (!empty($service)) ? true : false;
                case 'system/app':
                    $app = App::where($key, $value)->first();

                    return (!empty($app)) ? true : false;
                case 'system/event':
                case 'system/custom':
                case 'user/custom':
                case $service . '/_schema':
                    try {
                        $result = ServiceHandler::handleRequest(Verbs::GET, $service, $resource . '/' . $value);
                        if (is_string($result)) {
                            $result = ['value' => $result];
                        }
                        $result = array_get($result, config('df.resources_wrapper'), $result);

                        return (count($result) > 0) ? true : false;
                    } catch (NotFoundException $e) {
                        return false;
                    }
                default:
                    try {
                        $result =
                            ServiceHandler::handleRequest(Verbs::GET, $service, $resource,
                                ['filter' => "$key = $value"]);
                        if (is_string($result)) {
                            $result = ['value' => $result];
                        }
                        $result = array_get($result, config('df.resources_wrapper'), $result);

                        return (count($result) > 0) ? true : false;
                    } catch (NotFoundException $e) {
                        return false;
                    }
            }
        }

        return false;
    }
}