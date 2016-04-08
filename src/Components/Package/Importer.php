<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleServiceAccess;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\UserAppRole;
use DreamFactory\Core\Services\BaseFileService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Enums\Verbs;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Arr;

/**
 * Class Importer.
 * This class uses the Package instance and handles
 * everything to import package file.
 *
 * @package DreamFactory\Core\Components\Package
 */
class Importer
{
    /** @type \DreamFactory\Core\Components\Package\Package */
    protected $package;

    /**
     * Keeps an internal log of what's happening during the import process.
     *
     * @type array
     */
    protected $log = [];

    /**
     * When this is true, import skips record that already exists.
     *
     * @type bool
     */
    protected $ignoreExisting = true;

    /**
     * Importer constructor.
     *
     * @param Package $package        Package info (uploaded file array or url of file)
     * @param bool    $ignoreExisting Set true to ignore duplicates or false to throw exception.
     */
    public function __construct($package, $ignoreExisting = true)
    {
        $this->package = $package;
        $this->ignoreExisting = $ignoreExisting;
    }

    /**
     * Imports the packages.
     *
     * @throws \Exception
     */
    public function import()
    {
        \DB::beginTransaction();

        try {
            $this->insertRole();
            $this->insertService();
            $this->insertRoleServiceAccess();
            $this->insertApp();
            $this->insertUser();
            $this->insertUserAppRole();
            $this->insertOtherResource();
            $this->insertEventScripts();
            $this->storeFiles();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to import package. Rolling back. ' . $e->getMessage());
            throw $e;
        }

        \DB::commit();
    }

    /**
     * Returns the internal log.
     *
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Imports system/role
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
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

    /**
     * Imports system/user
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertUser()
    {
        $data = $this->package->getResourceFromZip('system/user.json');
        $users = $this->cleanDuplicates($data, 'system', 'user');

        if (!empty($users)) {
            try {
                foreach ($users as $i => $user) {
                    $this->fixCommonFields($user);
                    unset($user['user_to_app_to_role_by_user_id']);
                    $users[$i] = $user;
                }

                $payload = ResourcesWrapper::wrapResources($users);
                ServiceHandler::handleRequest(Verbs::POST, 'system', 'user', [], $payload);
                $this->updateUserPassword($users);

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException('Failed to insert users. ' . $e->getMessage());
            }
        } else {
            return false;
        }
    }

    /**
     * Updates user password when package is secured with a password.
     *
     * @param $users
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function updateUserPassword($users)
    {
        if ($this->package->isSecured() && !empty($users)) {
            $password = $this->package->getPassword();
            // Using md5 of password to use a 32 char long key for Encrypter.
            $crypt = new Encrypter(md5($password), config('app.cipher'));
            foreach ($users as $i => $user) {
                if (isset($user['password'])) {
                    /** @type User $model */
                    $model = User::where('email', '=', $user['email'])->first();
                    $model->updatePasswordHashUsingCrypto($user['password'], $crypt);
                }
            }
        }
    }

    /**
     * Imports user_to_app_to_role_by_user_id relation.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertUserAppRole()
    {
        $usersInZip = $this->package->getResourceFromZip('system/user.json');

        if (!empty($usersInZip)) {
            try {
                foreach ($usersInZip as $uiz) {
                    $uar = $uiz['user_to_app_to_role_by_user_id'];
                    $user = User::whereEmail($uiz['email'])->first();
                    $newUserId = $user->id;
                    if (!empty($user) && !empty($uar)) {
                        $cleanedUar = [];
                        foreach ($uar as $r) {
                            $originId = $r['id'];
                            $this->fixCommonFields($r);
                            $newRoleId = $this->getNewRoleId($r['role_id']);
                            $newAppId = $this->getNewAppId($r['app_id']);

                            if (empty($newRoleId) && !empty($r['role_id'])) {
                                $this->log(
                                    'warning',
                                    'Skipping relation user_to_app_to_role_by_user_id with id ' .
                                    $originId .
                                    ' for user ' .
                                    $uiz['email'] .
                                    '. Role not found for id ' .
                                    $r['role_id']
                                );
                                continue;
                            }

                            if (empty($newAppId) && !empty($r['app_id'])) {
                                $this->log(
                                    'warning',
                                    'Skipping relation user_to_app_to_role_by_user_id with id ' .
                                    $originId .
                                    ' for user ' .
                                    $uiz['email'] .
                                    '. App not found for id ' .
                                    $r['app_id']
                                );
                                continue;
                            }

                            $r['role_id'] = $newRoleId;
                            $r['app_id'] = $newAppId;
                            $r['user_id'] = $newUserId;

                            if ($this->isDuplicateUserAppRole($r)) {
                                $this->log(
                                    'notice',
                                    'Skipping duplicate user_to_app_to_role relation with id ' . $originId . '.'
                                );
                                continue;
                            }

                            $cleanedUar[] = $r;
                        }

                        $userUpdate = ['user_to_app_to_role_by_user_id' => $cleanedUar];
                        ServiceHandler::handleRequest(Verbs::PATCH, 'system', 'user/' . $newUserId, [], $userUpdate);
                    } elseif (!empty($uar) && empty($user)) {
                        $this->log(
                            'warning',
                            'Skipping all user_to_app_to_role_by_user_id relations for user ' .
                            $uiz['email'] .
                            ' No imported/existing user found.'
                        );
                        \Log::debug('Skipped user_to_app_to_role_by_user_id.', $uiz);
                    } else {
                        $this->log('notice', 'No user_to_app_to_role_by_user_id relation for user ' . $uiz['email']);
                    }
                }

                return true;
            } catch (\Exception $e) {
                throw new InternalServerErrorException(
                    'Failed to insert user_to_app_to_role_by_user_id relation for users. ' .
                    $e->getMessage()
                );
            }
        } else {
            return false;
        }
    }

    /**
     * Imports system/service
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertService()
    {
        $data = $this->package->getResourceFromZip('system/service.json');
        $services = $this->cleanDuplicates($data, 'system', 'service');

        if (!empty($services)) {
            $this->decryptServices($services);
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

    /**
     * Imports Role Service Access relations for role.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
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
                        $cleanedRsa = [];
                        foreach ($rsa as $r) {
                            $originId = $r['id'];
                            $this->fixCommonFields($r);
                            $newServiceId = $this->getNewServiceId($r['service_id']);

                            if (empty($newServiceId) && !empty($r['service_id'])) {
                                $this->log(
                                    'warning',
                                    'Skipping relation role_service_access_by_role_id with id ' .
                                    $originId .
                                    ' for Role. ' .
                                    $riz['name'] .
                                    '. Service not found for ' .
                                    $r['service_id']
                                );
                                continue;
                            }

                            $r['service_id'] = $newServiceId;
                            $r['role_id'] = $newRoleId;

                            if ($this->isDuplicateRoleServiceAccess($r)) {
                                $this->log(
                                    'notice',
                                    'Skipping duplicate role_service_access relation with id ' . $originId . '.'
                                );
                                continue;
                            }

                            $cleanedRsa[] = $r;
                        }

                        $roleUpdate = ['role_service_access_by_role_id' => $cleanedRsa];
                        ServiceHandler::handleRequest(Verbs::PATCH, 'system', 'role/' . $newRoleId, [], $roleUpdate);
                    } elseif (!empty($rsa) && empty($role)) {
                        $this->log(
                            'warning',
                            'Skipping all Role Service Access for ' . $riz['name'] . ' No imported role found.'
                        );
                        \Log::debug('Skipped Role Service Access.', $riz);
                    } else {
                        $this->log('notice', 'No Role Service Access for role ' . $riz['name']);
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

    /**
     * Checks for duplicate role_service_access relation.
     *
     * @param $rsa
     *
     * @return bool
     */
    protected function isDuplicateRoleServiceAccess($rsa)
    {
        $roleId = array_get($rsa, 'role_id');
        $serviceId = array_get($rsa, 'service_id');
        $component = array_get($rsa, 'component');
        $verbMask = array_get($rsa, 'verb_mask');
        $requestorMask = array_get($rsa, 'requestor_mask');

        if (is_null($serviceId)) {
            $servicePhrase = "service_id is NULL";
        } else {
            $servicePhrase = "service_id = '$serviceId'";
        }

        if (is_null($component)) {
            $componentPhrase = "component is NULL";
        } else {
            $componentPhrase = "component = '$component'";
        }

        $rsaRecord = RoleServiceAccess::whereRaw(
            "role_id = '$roleId' AND 
            $servicePhrase AND 
            $componentPhrase AND 
            verb_mask = '$verbMask' AND 
            requestor_mask = '$requestorMask'"
        )->first(['id']);

        return (empty($rsaRecord)) ? false : true;
    }

    /**
     * Checks for duplicate user_to_app_to_role relation.
     *
     * @param $uar
     *
     * @return bool
     */
    protected function isDuplicateUserAppRole($uar)
    {
        $userId = $uar['user_id'];
        $appId = $uar['app_id'];
        $roleId = $uar['role_id'];

        $uarRecord = UserAppRole::whereRaw(
            "user_id = '$userId' AND 
            role_id = '$roleId' AND 
            app_id = '$appId'"
        )->first(['id']);

        return (empty($uarRecord)) ? false : true;
    }

    /**
     * Imports system/app
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
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

    /**
     * Imports resources that does not need to inserted in a specific order.
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertOtherResource()
    {
        $items = $this->package->getNonStorageServices();

        foreach ($items as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                try {
                    $api = $service . '/' . $resourceName;
                    switch ($api) {
                        case 'system/app':
                        case 'system/role':
                        case 'system/service':
                        case 'system/event':
                        case 'system/user':
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
                } catch (UnauthorizedException $e) {
                    $this->log(
                        'error',
                        'Failed to insert resources for ' . $service . '/' . $resourceName . '. ' . $e->getMessage()
                    );
                }
            }
        }
    }

    /**
     * Imports system/event scripts.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
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

    /**
     * Imports generic resources.
     *
     * @param string $service
     * @param string $resource
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
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
                if ($service . '/' . $resource === 'system/admin') {
                    $this->updateUserPassword($records);
                }

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

    /**
     * Imports app files or other storage files from package.
     */
    protected function storeFiles()
    {
        $items = $this->package->getStorageServices();

        foreach ($items as $service => $resources) {
            if (is_string($resources)) {
                $resources = explode(',', $resources);
            }

            try {
                /** @type BaseFileService $storage */
                $storage = ServiceHandler::getService($service);
                foreach ($resources as $resource) {
                    try {
                        $resourcePath = $service . '/' . ltrim($resource, '/');
                        $file = $this->package->getFileFromZip($resourcePath);
                        if (!empty($file)) {
                            $storage->driver()
                                ->moveFile($storage->getContainerId(), ltrim($resource, '/'), $file, true);
                        } else {
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
                    } catch (\Exception $e) {
                        $this->log('warning',
                            'Skipping storage resource ' . $service . '/' . $resource . '. ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                $this->log('error', 'Failed to store files for service ' . $service . '. ' . $e->getMessage());
            }
        }
    }

    /**
     * Finds and returns the new role id by old id.
     *
     * @param int $oldRoleId
     *
     * @return int|null
     */
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

    /**
     * Finds and returns new App id by old App id.
     *
     * @param $oldAppId
     *
     * @return int|null
     */
    protected function getNewAppId($oldAppId)
    {
        if (empty($oldAppId)) {
            return null;
        }

        $apps = $this->package->getResourceFromZip('system/app.json');
        $appName = null;
        foreach ($apps as $app) {
            if ($oldAppId === $app['id']) {
                $appName = $app['name'];
                break;
            }
        }

        if (!empty($appName)) {
            $newApp = App::whereName($appName)->first(['id']);
            if (!empty($newApp)) {
                return $newApp->id;
            }
        }

        return null;
    }

    /**
     * Finds and returns the new service id by old id.
     *
     * @param int $oldServiceId
     *
     * @return int|null
     */
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
     * Stores internal log and write to system log.
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
        if (isset($record['last_modified_by_id'])) {
            unset($record['last_modified_by_id']);
        }
        if (isset($record['created_by_id'])) {
            $record['created_by_id'] = Session::getCurrentUserId();
        }
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
                strpos($key, 'role_service_access_by_') !== false ||
                strpos($key, 'user_to_app_to_role_by_') !== false
            ) {
                if (empty($value) || is_array($value)) {
                    unset($record[$key]);
                }
            }
        }
    }

    /**
     * Removes records from packaged resource that already exists
     * in the target instance.
     *
     * @param $data
     * @param $service
     * @param $resource
     *
     * @return array
     */
    protected function cleanDuplicates($data, $service, $resource)
    {
        $cleaned = [];
        if (!empty($data)) {
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
        }

        return $cleaned;
    }

    /**
     * Checks to see if a resource record is a duplicate.
     *
     * @param string $service
     * @param string $resource
     * @param mixed  $value
     * @param string $key
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
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

    /**
     * Decrypts services if package is secured with a password.
     *
     * @param $services
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    protected function decryptServices(& $services)
    {
        $secured = $this->package->isSecured();

        if ($secured) {
            $password = $this->package->getPassword();
            try {
                // Using md5 of password to use a 32 char long key for Encrypter.
                $crypt = new Encrypter(md5($password), config('app.cipher'));
                if (Arr::isAssoc($services)) {
                    if (isset($services['config'])) {
                        $services['config'] = static::decryptServiceConfig($services['config'], $crypt);
                    }
                } else {
                    foreach ($services as $i => $service) {
                        if (isset($service['config'])) {
                            $service['config'] = static::decryptServiceConfig($service['config'], $crypt);
                            $services[$i] = $service;
                        }
                    }
                }
            } catch (DecryptException $e) {
                throw new UnauthorizedException('Invalid password.');
            }
        }
    }

    /**
     * Decrypts service config when package is secure with a password.
     *
     * @param array                            $config
     * @param \Illuminate\Encryption\Encrypter $crypt
     *
     * @return array
     */
    protected static function decryptServiceConfig(array $config, Encrypter $crypt)
    {
        if (!empty($config)) {
            foreach ($config as $key => $value) {
                if (is_array($value)) {
                    $config[$key] = static::decryptServiceConfig($value, $crypt);
                } elseif (is_string($value)) {
                    $config[$key] = $crypt->decrypt($value);
                }
            }
        }

        return $config;
    }
}