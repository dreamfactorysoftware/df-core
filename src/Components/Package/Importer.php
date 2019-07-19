<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Contracts\FileServiceInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\DfException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\BaseModel;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleServiceAccess;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\UserAppRole;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use Illuminate\Contracts\Encryption\DecryptException;
use ServiceManager;

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
    protected $overwriteExisting = false;

    /**
     * A flag to indicate if an existing record was overwritten during import.
     *
     * @var bool
     */
    protected $overwrote = false;

    /**
     * Importer constructor.
     *
     * @param Package $package           Package info (uploaded file array or url of file)
     * @param bool    $overwriteExisting Set true to ignore duplicates or false to throw exception.
     */
    public function __construct($package, $overwriteExisting = false)
    {
        $this->package = $package;
        $this->overwriteExisting = $overwriteExisting;
    }

    /**
     * Imports the packages.
     *
     * @throws \Exception
     * @return bool
     */
    public function import()
    {
        \DB::beginTransaction();

        try {
            $imported = ($this->insertRole()) ?: false;
            $imported = ($this->insertService()) ?: $imported;
            $imported = ($this->insertRoleServiceAccess()) ?: $imported;
            $imported = ($this->insertApp()) ?: $imported;
            $imported = ($this->insertUser()) ?: $imported;
            $imported = ($this->insertUserAppRole()) ?: $imported;
            $imported = ($this->insertOtherResource()) ?: $imported;
            $imported = ($this->insertEventScripts()) ?: $imported;
            $imported = ($this->storeFiles()) ?: $imported;
            $imported = ($this->overwrote) ?: $imported;
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to import package. Rolling back. ' . $e->getMessage());

            throw $e;
        }

        \DB::commit();

        return $imported;
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
                $result = ServiceManager::handleRequest(
                    'system', Verbs::POST, 'role', [], [], $payload, null, true, true
                );
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }

                return true;
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log('error', 'Failed to insert roles. ' . $this->getErrorDetails($e));
//                } else {
//                    throw new InternalServerErrorException('Failed to insert roles. ' . $this->getErrorDetails($e));
//                }
                $this->throwExceptions($e, 'Failed to insert roles');
            }
        }

        return false;
    }

    /**
     * Imports system/user
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
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
                $result = ServiceManager::handleRequest(
                    'system', Verbs::POST, 'user', [], [], $payload, null, true, true
                );
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }
                static::updateUserPassword($users);

                return true;
            } catch (DecryptException $e) {
                throw new UnauthorizedException('Invalid password.');
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log('error', 'Failed to insert users. ' . $this->getErrorDetails($e));
//                } else {
//                    throw new InternalServerErrorException('Failed to insert users. ' . $this->getErrorDetails($e));
//                }
                $this->throwExceptions($e, 'Failed to insert users');
            }
        }

        return false;
    }

    /**
     * Updates user password when package is secured with a password.
     *
     * @param $users
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected static function updateUserPassword($users)
    {
        if (!empty($users)) {
            foreach ($users as $i => $user) {
                if (isset($user['password'])) {
                    /** @type User $model */
                    $model = User::where('email', '=', $user['email'])->first();
                    $model->updatePasswordHash($user['password']);
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
        $imported = false;

        if (!empty($usersInZip)) {
            try {
                foreach ($usersInZip as $uiz) {
                    $uar = $uiz['user_to_app_to_role_by_user_id'];
                    $user = User::whereEmail($uiz['email'])->first();
                    if (!empty($user) && !empty($uar)) {
                        $newUserId = $user->id;
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

                        if (!empty($cleanedUar)) {
                            $userUpdate = ['user_to_app_to_role_by_user_id' => $cleanedUar];
                            $result =
                                ServiceManager::handleRequest(
                                    'system', Verbs::PATCH, 'user/' . $newUserId, [], [], $userUpdate, null, true, true
                                );
                            if ($result->getStatusCode() >= 300) {
                                throw ResponseFactory::createExceptionFromResponse($result);
                            }

                            $imported = true;
                        }
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
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log(
//                        'error',
//                        'Failed to insert user_to_app_to_role_by_user_id relation for users. ' .
//                        $this->getErrorDetails($e)
//                    );
//                } else {
//                    throw new InternalServerErrorException(
//                        'Failed to insert user_to_app_to_role_by_user_id relation for users. ' .
//                        $this->getErrorDetails($e)
//                    );
//                }
                $this->throwExceptions($e, 'Failed to insert user_to_app_to_role_by_user_id relation for users');
            }
        }

        return $imported;
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
            try {
                foreach ($services as $i => $service) {
                    unset($service['id']);
                    unset($service['last_modified_by_id']);
                    $service['created_by_id'] = Session::getCurrentUserId();
                    if (!empty($oldRoleId = array_get($service, 'config.default_role'))) {
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
                    if (!empty($oldDoc = array_get($service, 'doc'))) {
                        $service['service_doc_by_service_id'] = $oldDoc;
                        unset($service['doc']);
                    }
                    $services[$i] = $service;
                }

                $payload = ResourcesWrapper::wrapResources($services);
                $result = ServiceManager::handleRequest(
                    'system', Verbs::POST, 'service', [], [], $payload, null, true, true
                );
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }

                return true;
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log('error', "Failed to insert services. " . $this->getErrorDetails($e));
//                } else {
//                    throw new InternalServerErrorException("Failed to insert services. " . $this->getErrorDetails($e));
//                }
                $this->throwExceptions($e, 'Failed to insert services');
            }
        }

        return false;
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
        $imported = false;

        if (!empty($rolesInZip)) {
            try {
                foreach ($rolesInZip as $riz) {
                    $rsa = $riz['role_service_access_by_role_id'];
                    $role = Role::whereName($riz['name'])->first();
                    if (!empty($role) && !empty($rsa)) {
                        $newRoleId = $role->id;
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
                                    ' for Role ' .
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

                        if (!empty($cleanedRsa)) {
                            $roleUpdate = ['role_service_access_by_role_id' => $cleanedRsa];
                            $result =
                                ServiceManager::handleRequest(
                                    'system', Verbs::PATCH, 'role/' . $newRoleId, [], [], $roleUpdate, null, true, true
                                );
                            if ($result->getStatusCode() >= 300) {
                                throw ResponseFactory::createExceptionFromResponse($result);
                            }
                            $imported = true;
                        }
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
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log(
//                        'error',
//                        'Failed to insert role service access records for roles. ' .
//                        $this->getErrorDetails($e)
//                    );
//                }
//                throw new InternalServerErrorException(
//                    'Failed to insert role service access records for roles. ' .
//                    $this->getErrorDetails($e)
//                );
                $this->throwExceptions($e, 'Failed to insert role service access records for roles');
            }
        }

        return $imported;
    }

    /**
     * Returns details from exception.
     *
     * @param \Exception $e
     * @param bool       $trace
     *
     * @return string
     */
    protected function getErrorDetails(\Exception $e, $trace = false)
    {
        $msg = $e->getMessage();
        if ($e instanceof DfException) {
            $context = $e->getContext();
            if (is_array($context)) {
                $context = print_r($context, true);
            }
            if (!empty($context)) {
                $msg .= "\nContext: " . $context;
            }
        }

        if ($trace === true) {
            $msg .= "\nTrace:\n" . $e->getTraceAsString();
        }

        return $msg;
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

        return RoleServiceAccess::whereRaw(
            "role_id = '$roleId' AND 
            $servicePhrase AND 
            $componentPhrase AND 
            verb_mask = '$verbMask' AND 
            requestor_mask = '$requestorMask'"
        )->exists();
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

        return UserAppRole::whereRaw(
            "user_id = '$userId' AND 
            role_id = '$roleId' AND 
            app_id = '$appId'"
        )->exists();
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

                    $apiKey = $app['api_key'];
                    if (!empty($apiKey) && !App::isApiKeyUnique($apiKey)) {
                        $this->log(
                            'notice',
                            'Duplicate API Key found for app ' . $app['name'] . '. Regenerating API Key.'
                        );
                    }

                    $app['storage_service_id'] = $newStorageId;
                    $app['role_id'] = $newRoleId;
                    $apps[$i] = $app;
                }

                $payload = ResourcesWrapper::wrapResources($apps);
                $result = ServiceManager::handleRequest(
                    'system', Verbs::POST, 'app', [], [], $payload, null, true, true
                );
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }

                return true;
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log('error', 'Failed to insert apps. ' . $this->getErrorDetails($e));
//                } else {
//                    throw new InternalServerErrorException('Failed to insert apps. ' . $this->getErrorDetails($e));
//                }
                $this->throwExceptions($e, 'Failed to insert apps');
            }
        }

        return false;
    }

    /**
     * Imports resources that does not need to inserted in a specific order.
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertOtherResource()
    {
        $items = $this->package->getNonStorageServices();
        $imported = false;

        foreach ($items as $service => $resources) {
            foreach ($resources as $resourceName => $details) {
                try {
                    $api = $service . '/' . $resourceName;
                    switch ($api) {
                        case 'system/app':
                        case 'system/role':
                        case 'system/service':
                        case 'system/event_script':
                        case 'system/user':
                            // Skip; already imported at this point.
                            break;
                        case $service . '/_table':
                            $imported = $this->insertDbTableResources($service);
                            break;
                        case $service . '/_proc':
                        case $service . '/_func':
                            // Not supported at this time.
                            $this->log('warning', 'Skipping resource ' . $resourceName . '. Not supported.');
                            break;
                        default:
                            $imported = $this->insertGenericResources($service, $resourceName);
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

        return $imported;
    }

    /**
     * Insert DB table data.
     *
     * @param $service
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertDbTableResources($service)
    {
        $data = $this->package->getResourceFromZip($service . '/_table' . '.json');
        if (!empty($data)) {
            foreach ($data as $table) {
                $tableName = array_get($table, 'name');
                $resource = '_table/' . $tableName;
                $records = array_get($table, 'record');
                $records = $this->cleanDuplicates($records, $service, $resource);

                if (!empty($records)) {
                    try {
                        foreach ($records as $i => $record) {
                            $this->fixCommonFields($record, false);
                            $this->unsetImportedRelations($record);
                            $records[$i] = $record;
                        }

                        $payload = ResourcesWrapper::wrapResources($records);
                        $result = ServiceManager::handleRequest(
                            $service,
                            Verbs::POST,
                            $resource,
                            ['continue' => true],
                            [],
                            $payload,
                            null,
                            true,
                            true
                        );
                        if ($result->getStatusCode() >= 300) {
                            throw ResponseFactory::createExceptionFromResponse($result);
                        }
                    } catch (\Exception $e) {
//                        if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                            $this->log(
//                                'error',
//                                'Failed to insert ' .
//                                $service .
//                                '/' .
//                                $resource .
//                                '. ' .
//                                $this->getErrorDetails($e)
//                            );
//                        } else {
//                            throw new InternalServerErrorException('Failed to insert ' .
//                                $service .
//                                '/' .
//                                $resource .
//                                '. ' .
//                                $this->getErrorDetails($e)
//                            );
//                        }
                        $this->throwExceptions($e, 'Failed to insert ' . $service . '/' . $resource);
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Imports system/event_scripts.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function insertEventScripts()
    {
        try {

            if (!class_exists(\DreamFactory\Core\Script\ServiceProvider::class)) throw new ForbiddenException('Upgrade to a paid license to import event scripts.');

            if (empty($data = $this->package->getResourceFromZip('system/event_script.json'))) {
                // pre-2.3.0 version
                $data = $this->package->getResourceFromZip('system/event.json');
            }
            $scripts = $this->cleanDuplicates($data, 'system', 'event_script');

            if (!empty($scripts)) {
                foreach ($scripts as $script) {
                    $name = array_get($script, 'name');
                    $this->fixCommonFields($script);
                    $result =
                        ServiceManager::handleRequest(
                            'system', Verbs::POST, 'event_script/' . $name, [], [], $script, null, true, true
                        );
                    if ($result->getStatusCode() >= 300) {
                        throw ResponseFactory::createExceptionFromResponse($result);
                    }
                }
                return true;
            }
        } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log('error', 'Failed to insert event_script. ' . $this->getErrorDetails($e));
//                } else {
//                    throw new InternalServerErrorException(
//                        'Failed to insert event_script. ' .
//                        $this->getErrorDetails($e)
//                    );
//                }
            $this->throwExceptions($e, 'Failed to insert event_script');
        }

        return false;
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
        $merged = $this->mergeSchemas($service, $resource, $data);
        $records = $this->cleanDuplicates($data, $service, $resource);

        if (!empty($records)) {
            try {
                foreach ($records as $i => $record) {
                    $this->fixCommonFields($record);
                    $this->unsetImportedRelations($record);
                    $records[$i] = $record;
                }

                $payload = ResourcesWrapper::wrapResources($records);
                $result = ServiceManager::handleRequest(
                    $service, Verbs::POST, $resource, ['continue' => true], [], $payload, null, true, true
                );
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }
                if ($service . '/' . $resource === 'system/admin') {
                    static::updateUserPassword($records);
                }

                return true;
            } catch (\Exception $e) {
//                if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
//                    $this->log(
//                        'error',
//                        'Failed to insert ' .
//                        $service .
//                        '/' .
//                        $resource .
//                        '. ' .
//                        $this->getErrorDetails($e)
//                    );
//                } else {
//                    throw new InternalServerErrorException('Failed to insert ' .
//                        $service .
//                        '/' .
//                        $resource .
//                        '. ' .
//                        $this->getErrorDetails($e)
//                    );
//                }
                $this->throwExceptions($e, 'Failed to insert ' . $service . '/' . $resource);
            }
        }

        return $merged;
    }

    /**
     * Merges any schema changes.
     *
     * @param $service
     * @param $resource
     * @param $data
     *
     * @return bool
     */
    protected function mergeSchemas($service, $resource, $data)
    {
        $merged = false;
        if ('db/_schema' === $service . '/' . $resource) {
            $payload =
                (true === config('df.always_wrap_resources')) ? [config('df.resources_wrapper') => $data] : $data;
            $result = ServiceManager::handleRequest($service, Verbs::PATCH, $resource, [], [], $payload);
            if ($result->getStatusCode() === 200) {
                $merged = true;
            }
        }

        return $merged;
    }

    /**
     * Imports app files or other storage files from package.
     */
    protected function storeFiles()
    {
        $items = $this->package->getStorageServices();
        $stored = false;

        foreach ($items as $service => $resources) {
            if (is_string($resources)) {
                $resources = explode(',', $resources);
            }

            try {
                /** @type FileServiceInterface $storage */
                $storage = ServiceManager::getService($service);
                foreach ($resources as $resource) {
                    try {
                        // checkServicePermission throws exception below if action not allowed for the user.
                        Session::checkServicePermission(
                            Verbs::POST, $service, trim($resource, '/'),
                            Session::getRequestor()
                        );

                        $resourcePath = $service . '/' . ltrim($resource, '/');
                        $file = $this->package->getFileFromZip($resourcePath);
                        if (!empty($file)) {
                            $storage->moveFile(ltrim($resource, '/'), $file, true);
                        } else {
                            $resourcePath = $service . '/' . trim($resource, '/') . '/' . md5($resource) . '.zip';
                            $zip = $this->package->getZipFromZip($resourcePath);
                            if (!empty($zip)) {
                                $storage->extractZipFile(
                                    rtrim($resource, '/') . '/',
                                    $zip,
                                    false,
                                    rtrim($resource, '/') . '/'
                                );
                            }
                        }
                        $stored = true;
                    } catch (\Exception $e) {
                        // Not throwing exceptions here. File storage process is not
                        // transactional. There is no way to rollback if exception
                        // is thrown in the middle of import process. Instead, log
                        // the error and finish the process.
                        $logLevel = 'warning';
                        if ($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN) {
                            $logLevel = 'error';
                        }
                        $this->log(
                            $logLevel,
                            'Skipping storage resource ' . $service . '/' . $resource . '. ' . $e->getMessage()
                        );
                    }
                }
            } catch (\Exception $e) {
                $this->log('error', 'Failed to store files for service ' . $service . '. ' . $e->getMessage());
            }
        }

        return $stored;
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
        } else {
            $existingApp = App::find($oldAppId);
            if (!empty($existingApp)) {
                $appName = $existingApp->name;

                if (in_array($appName, ['admin', 'api_docs', 'file_manager'])) {
                    // If app is one of the pre-defined system apps
                    // then new id is most likely the same as the old id.
                    return $oldAppId;
                }
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
            if (!empty($id = ServiceManager::getServiceIdByName($serviceName))) {
                return $id;
            }
        } else {
            if (!empty($serviceName = ServiceManager::getServiceNameById($oldServiceId))) {
                if (in_array($serviceName, ['system', 'api_docs', 'files', 'db', 'email', 'user'])) {
                    // If service is one of the pre-defined system services
                    // then new id is most likely the same as the old id.
                    return $oldServiceId;
                }
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
     * @param bool  $unsetIdField
     */
    protected function fixCommonFields(array & $record, $unsetIdField = true)
    {
        if ($unsetIdField) {
            unset($record['id']);
        }
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
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function cleanDuplicates($data, $service, $resource)
    {
        $cleaned = [];
        if (!empty($data)) {
            $rSeg = explode('/', $resource);
            $api = $service . '/' . $resource;

            switch ($api) {
                case 'system/admin':
                case 'system/user':
                    $key = 'email';
                    break;
                case 'system/cors':
                    $key = 'path';
                    break;
                case $service . '/_table/' . array_get($rSeg, 1);
                    $key = 'id';
                    break;
                default:
                    $key = 'name';
            }

            foreach ($data as $rec) {
                if (isset($rec[$key])) {
                    $value = array_get($rec, $key);
                    if (!$this->isDuplicate($service, $resource, array_get($rec, $key), $key)) {
                        $cleaned[] = $rec;
                    } elseif ($this->overwriteExisting === true) {
                        try {
                            if (true === static::patchExisting($service, $resource, $rec, $key)) {
                                $this->overwrote = true;
                                $this->log(
                                    'notice',
                                    'Overwrote duplicate found for ' . $api . ' with ' . $key . ' ' . $value
                                );
                            }
                        } catch (RestException $e) {
                            $this->throwExceptions(
                                $e,
                                'An unexpected error occurred. ' .
                                'Could not overwrite an existing ' .
                                $api . ' resource with ' . $key . ' ' .
                                $value . '. It could be due to your existing resource being exactly ' .
                                'same as your overwriting resource. Try deleting your existing resource and re-import.'
                            );
                        }
                    } else {
                        $this->log(
                            'notice',
                            'Ignored duplicate found for ' .
                            $api .
                            ' with ' . $key .
                            ' ' . $value
                        );
                    }
                } else {
                    $cleaned[] = $rec;
                    $this->log(
                        'warning',
                        'Skipped duplicate check for ' .
                        $api .
                        ' by key/field ' . $key .
                        '. Key/Field is not set'
                    );
                }
            }
        }

        return $cleaned;
    }

    /**
     * @param \Exception $e
     * @param null       $genericMsg
     * @param bool       $trace
     *
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function throwExceptions(\Exception $e, $genericMsg = null, $trace = false)
    {
        $msg = 'An error occurred. ';
        if(!empty($genericMsg)){
            $msg = rtrim(trim($genericMsg), '.') . '. ';
        }
        $errorMessage = $this->getErrorDetails($e, $trace);
        $msg .= $errorMessage;
        if($e->getCode() === HttpStatusCodes::HTTP_FORBIDDEN){
            throw new ForbiddenException($msg);
        } else {
            throw new InternalServerErrorException($msg);
        }
    }

    /**
     * @param $service
     * @param $resource
     * @param $record
     * @param $key
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected static function patchExisting($service, $resource, $record, $key)
    {
        $api = $service . '/' . $resource;
        $value = array_get($record, $key);
        switch ($api) {
            case 'system/event_script':
            case 'system/custom':
            case 'user/custom':
            case $service . '/_schema':
                $result = ServiceManager::handleRequest(
                    $service,
                    Verbs::PATCH,
                    $resource . '/' . $value,
                    [],
                    [],
                    $record,
                    null,
                    true,
                    true
                );
                if ($result->getStatusCode() === 404) {
                    throw new InternalServerErrorException(
                        'Could not find existing resource to PATCH for ' .
                        $service . '/' . $resource . '/' . $value
                    );
                }
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }

                return true;
            default:
                /** @var ServiceResponseInterface $result */
                $result = ServiceManager::handleRequest(
                    $service,
                    Verbs::GET,
                    $resource,
                    ['filter' => "$key='$value'"],
                    [],
                    null,
                    null,
                    true,
                    true
                );
                if ($result->getStatusCode() === 404) {
                    throw new InternalServerErrorException(
                        'Could not find existing resource for ' .
                        $service . '/' . $resource .
                        ' using ' . $key . ' = ' . $value
                    );
                }
                if ($result->getStatusCode() >= 300) {
                    throw ResponseFactory::createExceptionFromResponse($result);
                }
                $content = ResourcesWrapper::unwrapResources($result->getContent());
                $existing = array_get($content, 0);
                $existingId = array_get($existing, BaseModel::getPrimaryKeyStatic());
                if (!empty($existingId)) {
                    unset($record[BaseModel::getPrimaryKeyStatic()]);
                    $payload = $record;
                    if (in_array($api, ['system/admin', 'system/user'])) {
                        unset($payload['password']);
                    }
                    $result = ServiceManager::handleRequest(
                        $service,
                        Verbs::PATCH,
                        $resource . '/' . $existingId,
                        [],
                        [],
                        $payload,
                        null,
                        true,
                        true
                    );
                    if ($result->getStatusCode() === 404) {
                        throw new InternalServerErrorException(
                            'Could not find existing resource to PATCH for ' .
                            $service . '/' . $resource . '/' . $existingId
                        );
                    }
                    if ($result->getStatusCode() >= 300) {
                        throw ResponseFactory::createExceptionFromResponse($result);
                    }

                    if (in_array($api, ['system/admin', 'system/user'])) {
                        static::updateUserPassword([$record]);
                    }

                    return true;
                } else {
                    throw new InternalServerErrorException(
                        'Could not get ID for ' .
                        $service . '/' . $resource .
                        ' resource using ID field ' . BaseModel::getPrimaryKeyStatic()
                    );
                }
                break;
        }
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
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected function isDuplicate($service, $resource, $value, $key = 'name')
    {
        $api = $service . '/' . $resource;
        switch ($api) {
            case 'system/role':
                return Role::where($key, $value)->exists();
            case 'system/service':
                return Service::where($key, $value)->exists();
            case 'system/app':
                return App::where($key, $value)->exists();
            case 'system/event_script':
            case 'system/custom':
            case 'user/custom':
            case $service . '/_schema':
                try {
                    $result = ServiceManager::handleRequest($service, Verbs::GET, $resource . '/' . $value);
                    if ($result->getStatusCode() === 404) {
                        return false;
                    }
                    if ($result->getStatusCode() >= 300) {
                        throw ResponseFactory::createExceptionFromResponse($result);
                    }

                    $result = $result->getContent();
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
                    $result = ServiceManager::handleRequest($service, Verbs::GET, $resource,
                        ['filter' => "$key = $value"]);
                    if ($result->getStatusCode() === 404) {
                        return false;
                    }
                    if ($result->getStatusCode() >= 300) {
                        throw ResponseFactory::createExceptionFromResponse($result);
                    }

                    $result = $result->getContent();
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
}