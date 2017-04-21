<?php

namespace DreamFactory\Core\Utility;

use Carbon\Carbon;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\AppLookup;
use DreamFactory\Core\Models\Lookup;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleLookup;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\UserAppRole;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\UserLookup;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\Verbs;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class Session
{
    /**
     * @param string $action    - REST API action name
     * @param string $service   - API name of the service
     * @param string $component - API component/resource name
     * @param int    $requestor - Entity type requesting the service
     *
     * @throws ForbiddenException
     */
    public static function checkServicePermission(
        $action,
        $service,
        $component = null,
        $requestor = ServiceRequestorTypes::API
    ) {
        $verb = VerbsMask::toNumeric(static::cleanAction($action));

        $mask = static::getServicePermissions($service, $component, $requestor);

        if (!($verb & $mask)) {
            $msg = ucfirst($action) . " access to ";
            if (!empty($component)) {
                $msg .= "component '$component' of ";
            }

            $msg .= "service '$service' is not allowed by this user's role.";

            throw new ForbiddenException($msg);
        }
    }

    /**
     * @param string $service   - API name of the service
     * @param string $component - API component/resource name
     * @param int    $requestor - Entity type requesting the service
     *
     * @returns int|boolean
     */
    public static function getServicePermissions($service, $component = null, $requestor = ServiceRequestorTypes::API)
    {
        if (static::isSysAdmin()) {
            return
                VerbsMask::NONE_MASK |
                VerbsMask::GET_MASK |
                VerbsMask::POST_MASK |
                VerbsMask::PUT_MASK |
                VerbsMask::PATCH_MASK |
                VerbsMask::DELETE_MASK;
        }

        $roleId = Session::getRoleId();
        if ($roleId && !Role::getCachedInfo($roleId, 'is_active')) {
            return false;
        }

        $services = (array)static::get('role.services');
        $service = strval($service);
        $component = strval($component);

        //  If exact match found take it, otherwise follow up the chain as necessary
        //  All - Service - Component - Sub-component
        $allAllowed = VerbsMask::NONE_MASK;
        $allFound = false;
        $serviceAllowed = VerbsMask::NONE_MASK;
        $serviceFound = false;
        $componentAllowed = VerbsMask::NONE_MASK;
        $componentFound = false;
        $exactAllowed = VerbsMask::NONE_MASK;
        $exactFound = false;
        foreach ($services as $svcInfo) {
            $tempRequestors = array_get($svcInfo, 'requestor_mask', ServiceRequestorTypes::API);
            if (!($requestor & $tempRequestors)) {
                //  Requestor type not found in allowed requestors, skip access setting
                continue;
            }

            $tempService = strval(array_get($svcInfo, 'service'));
            $tempComponent = strval(array_get($svcInfo, 'component'));
            $tempVerbs = array_get($svcInfo, 'verb_mask');

            if (0 == strcasecmp($service, $tempService)) {
                if (!empty($component)) {
                    if (0 == strcasecmp($component, $tempComponent)) {
                        // exact match
                        $exactAllowed |= $tempVerbs;
                        $exactFound = true;
                    } elseif (($starPos = strpos($tempComponent, '*')) &&
                        (0 == strcasecmp(substr($component, 0, $starPos) . '*', $tempComponent))
                    ) {
                        $componentAllowed |= $tempVerbs;
                        $componentFound = true;
                    } elseif (($parenPos = strpos($component, '(')) &&
                        (0 == strcasecmp(substr($component, 0, $parenPos), $tempComponent))
                    ) {
                        // for resources called with options like foo() or foo(x, y, z)
                        $componentAllowed |= $tempVerbs;
                        $componentFound = true;
                    } elseif ('*' == $tempComponent) {
                        $serviceAllowed |= $tempVerbs;
                        $serviceFound = true;
                    }
                } else {
                    if (empty($tempComponent)) {
                        // exact match
                        $exactAllowed |= $tempVerbs;
                        $exactFound = true;
                    } elseif ('*' == $tempComponent) {
                        $serviceAllowed |= $tempVerbs;
                        $serviceFound = true;
                    }
                }
            } else {
                if (empty($tempService) && (('*' == $tempComponent) || (empty($tempComponent) && empty($component)))
                ) {
                    $allAllowed |= $tempVerbs;
                    $allFound = true;
                }
            }
        }

        if ($exactFound) {
            return $exactAllowed;
        } elseif ($componentFound) {
            return $componentAllowed;
        } elseif ($serviceFound) {
            return $serviceAllowed;
        } elseif ($allFound) {
            return $allAllowed;
        }

        return VerbsMask::NONE_MASK;
    }

    /**
     * @param string $service   - API name of the service
     * @param string $component - API component/resource name
     * @param int    $requestor - Entity type requesting the service
     *
     * @returns boolean
     */
    public static function checkForAnyServicePermissions(
        $service,
        $component = null,
        $requestor = ServiceRequestorTypes::API
    ) {
        if (static::isSysAdmin()) {
            return true;
        }

        $roleId = Session::getRoleId();
        if ($roleId && !Role::getCachedInfo($roleId, 'is_active')) {
            return false;
        }

        $services = (array)static::get('role.services');
        $service = strval($service);
        $component = strval($component);

        //  If exact match found take it, otherwise follow up the chain as necessary
        //  All - Service - Component - Sub-component
        $allAllowed = VerbsMask::NONE_MASK;
        $allFound = false;
        $serviceAllowed = VerbsMask::NONE_MASK;
        $serviceFound = false;
        $componentAllowed = VerbsMask::NONE_MASK;
        $componentFound = false;
        $exactAllowed = VerbsMask::NONE_MASK;
        $exactFound = false;
        foreach ($services as $svcInfo) {
            $tempRequestors = array_get($svcInfo, 'requestor_mask', ServiceRequestorTypes::API);
            if (!($requestor & $tempRequestors)) {
                //  Requestor type not found in allowed requestors, skip access setting
                continue;
            }

            $tempService = strval(array_get($svcInfo, 'service'));
            $tempComponent = strval(array_get($svcInfo, 'component'));
            $tempCompStarPos = strpos($tempComponent, '*');
            $tempVerbs = array_get($svcInfo, 'verb_mask');

            if (0 == strcasecmp($service, $tempService)) {
                if (!empty($component)) {
                    if (0 == strcasecmp($component, $tempComponent)) {
                        // exact match
                        $exactAllowed |= $tempVerbs;
                        $exactFound = true;
                    } elseif ($tempCompStarPos &&
                        (0 == strcasecmp(substr($component . '/', 0, $tempCompStarPos) . '*', $tempComponent))
                    ) {
                        $componentAllowed |= $tempVerbs;
                        $componentFound = true;
                    } elseif ('*' == $tempComponent) {
                        $serviceAllowed |= $tempVerbs;
                        $serviceFound = true;
                    }
                } else {
                    $serviceAllowed |= $tempVerbs;
                    $serviceFound = true;
                }
            } else {
                if (empty($tempService) && (('*' == $tempComponent) || (empty($tempComponent) && empty($component)))
                ) {
                    $allAllowed |= $tempVerbs;
                    $allFound = true;
                }
            }
        }

        if ($exactFound) {
            return (VerbsMask::NONE_MASK !== $exactAllowed);
        } elseif ($componentFound) {
            return (VerbsMask::NONE_MASK !== $componentAllowed);
        } elseif ($serviceFound) {
            return (VerbsMask::NONE_MASK !== $serviceAllowed);
        } elseif ($allFound) {
            return (VerbsMask::NONE_MASK !== $allAllowed);
        }

        return false;
    }

    /**
     * @param string $action - requested REST action
     *
     * @return string
     */
    protected static function cleanAction($action)
    {
        // check for non-conformists
        $action = strtoupper($action);
        switch ($action) {
            case 'READ':
                return Verbs::GET;

            case 'CREATE':
                return Verbs::POST;

            case 'UPDATE':
                return Verbs::PUT;
        }

        return $action;
    }

    /**
     * @param string $action
     * @param string $service
     * @param string $component
     *
     * @returns array
     */
    public static function getServiceFilters($action, $service, $component = null)
    {
        if (static::isSysAdmin()) {
            return [];
        }

        $services = (array)static::get('role.services');

        $serviceAllowed = null;
        $serviceFound = false;
        $componentFound = false;
        $action = VerbsMask::toNumeric(static::cleanAction($action));

        foreach ($services as $svcInfo) {
            $tempService = array_get($svcInfo, 'service');
            if (null === $tempVerbs = array_get($svcInfo, 'verb_mask')) {
                //  Check for old verbs array
                if (null !== $temp = array_get($svcInfo, 'verbs')) {
                    $tempVerbs = VerbsMask::arrayToMask($temp);
                }
            }

            if (0 == strcasecmp($service, $tempService)) {
                $serviceFound = true;
                $tempComponent = array_get($svcInfo, 'component');
                if (!empty($component)) {
                    if (0 == strcasecmp($component, $tempComponent)) {
                        $componentFound = true;
                        if ($tempVerbs & $action) {
                            $filters = array_get($svcInfo, 'filters');
                            $operator = array_get($svcInfo, 'filter_op', 'AND');
                            if (empty($filters)) {
                                return null;
                            }

                            return ['filters' => $filters, 'filter_op' => $operator];
                        }
                    } elseif (empty($tempComponent) || ('*' == $tempComponent)) {
                        if ($tempVerbs & $action) {
                            $filters = array_get($svcInfo, 'filters');
                            $operator = array_get($svcInfo, 'filter_op', 'AND');
                            if (empty($filters)) {
                                return null;
                            }

                            $serviceAllowed = ['filters' => $filters, 'filter_op' => $operator];
                        }
                    }
                } else {
                    if (empty($tempComponent) || ('*' == $tempComponent)) {
                        if ($tempVerbs & $action) {
                            $filters = array_get($svcInfo, 'filters');
                            $operator = array_get($svcInfo, 'filter_op', 'AND');
                            if (empty($filters)) {
                                return null;
                            }

                            $serviceAllowed = ['filters' => $filters, 'filter_op' => $operator];
                        }
                    }
                }
            }
        }

        if ($componentFound) {
            // at least one service and component match was found, but not the right verb

            return null;
        } elseif ($serviceFound) {
            return $serviceAllowed;
        }

        return null;
    }

    /**
     * @param array $systemLookup
     * @param array $appLookup
     * @param array $roleLookup
     * @param array $userLookup
     *
     * @return array
     */
    public static function combineLookups($systemLookup = [], $appLookup = [], $roleLookup = [], $userLookup = [])
    {
        $lookup = [];
        $secretLookup = [];

        static::addLookupsToMap(Lookup::class, $systemLookup, $lookup, $secretLookup);
        static::addLookupsToMap(RoleLookup::class, $roleLookup, $lookup, $secretLookup);
        static::addLookupsToMap(AppLookup::class, $appLookup, $lookup, $secretLookup);
        static::addLookupsToMap(UserLookup::class, $userLookup, $lookup, $secretLookup);

        return [
            'lookup'        => $lookup,
            'lookup_secret' => $secretLookup //Actual values of the secret keys. For internal use only.
        ];
    }

    /**
     * @param       $model
     * @param       $lookups
     * @param array $map
     * @param array $mapSecret
     */
    protected static function addLookupsToMap($model, $lookups, array &$map, array &$mapSecret)
    {
        foreach ($lookups as $lookup) {
            if ($lookup['private']) {
                /** @noinspection PhpUndefinedMethodInspection */
                $secretLookup = $model::find($lookup['id']);
                $mapSecret[$lookup['name']] = $secretLookup->value;
            } else {
                $map[$lookup['name']] = $lookup['value'];
            }
        }
    }

    /**
     * @param string $lookup
     * @param string $value
     * @param bool   $use_private
     *
     * @returns bool
     */
    public static function getLookupValue($lookup, &$value, $use_private = false)
    {
        if (empty($lookup)) {
            return false;
        }

        $_parts = explode('.', $lookup);
        if (count($_parts) > 1) {
            $_section = array_shift($_parts);
            $_lookup = implode('.', $_parts);
            if (!empty($_section)) {
                switch ($_section) {
                    case 'session':
                        switch ($_lookup) {
                            case 'id':
                            case 'token':
                                $value = static::getSessionToken();

                                return true;

//                            case 'ticket':
//                                $value = static::_generateTicket();
//
//                                return true;
                        }
                        break;

                    case 'user':
                    case 'role':
                        // get fields here
                        if (!empty($_lookup)) {
                            $info = static::get($_section);
                            if (isset($info, $info[$_lookup])) {
                                $value = $info[$_lookup];

                                return true;
                            }
                        }
                        break;

                    case 'app':
                        switch ($_lookup) {
                            case 'id':
                                $value = static::get('app.id');;

                                return true;

                            case 'api_key':
                                $value = static::getApiKey();

                                return true;
                        }
                        break;

                    case 'df':
                        switch ($_lookup) {
                            case 'host_url':
                                $value = Curl::currentUrl(false, false);

                                return true;
                            case 'name':
                                $value = \Config::get('df.instance_name', gethostname());

                                return true;
                            case 'version':
                                $value = \Config::get('df.version');

                                return true;
                            case 'api_version':
                                $value = \Config::get('df.api_version');

                                return true;
                            case 'confirm_invite_url':
                                $value = url(\Config::get('df.confirm_invite_url'));

                                return true;
                            case 'confirm_register_url':
                                $value = url(\Config::get('df.confirm_register_url'));

                                return true;
                            case 'confirm_reset_url':
                                $value = url(\Config::get('df.confirm_reset_url'));

                                return true;
                        }
                        break;
                }
            }
        }

        if ($use_private) {
            $lookups = static::get('lookup_secret');
            if (isset($lookups, $lookups[$lookup])) {
                $value = $lookups[$lookup];

                return true;
            }
        }
        // non-private
        $lookups = static::get('lookup');
        if (isset($lookups, $lookups[$lookup])) {
            $value = $lookups[$lookup];

            return true;
        }

        return false;
    }

    /**
     * @param string|array $subject
     * @param bool         $use_private
     *
     * @return string|array
     */
    public static function translateLookups($subject, $use_private = false)
    {
        static::replaceLookups($subject, $use_private);

        return $subject;
    }

    /**
     * @param string|array $subject
     * @param bool         $use_private
     */
    public static function replaceLookups(&$subject, $use_private = false)
    {
        if (is_string($subject)) {
            // filter string values should be wrapped in curly braces
            if (false !== strpos($subject, '{')) {
                $search = [];
                $replace = [];
                if (0 < preg_match_all('/{\w+[\(|\w|\.\-|\)]*}/', $subject, $targets)) {
                    foreach (current($targets) as $target) {
                        if (0 < preg_match_all('/[\w|\.]+/', $target, $words)) {
                            $words = current($words);
                            switch (count($words)) {
                                case 0:
                                    break;
                                case 1:
                                    if (static::getLookupValue($words[0], $value, $use_private)) {
                                        $search[] = $target;
                                        $replace[] = $value;
                                    }
                                    break;
                                default:
                                    $lookup = array_pop($words);
                                    if (static::getLookupValue($lookup, $value, $use_private)) {
                                        do {
                                            $word = array_pop($words);
                                            if (function_exists($word) &&
                                                in_array($word, config('df.lookup.allowed_modifiers', []))
                                            ) {
                                                $value = $word($value);
                                            }
                                        } while (!empty($words));
                                        $search[] = $target;
                                        $replace[] = $value;
                                    }
                                    break;
                            }
                        }
                    }
                }
                if (!empty($search)) {
                    $subject = str_replace($search, $replace, $subject);
                }
            }
        } elseif (is_array($subject)) {
            foreach ($subject as &$value) {
                static::replaceLookups($value, $use_private);
            }
        }
    }

    /**
     * @param array   $credentials
     * @param bool    $remember
     * @param bool    $login
     * @param integer $appId
     *
     * @return bool
     * @throws \Exception
     */
    public static function authenticate(array $credentials, $remember = false, $login = true, $appId = null)
    {
        if (\Auth::attempt($credentials, false, false)) {
            $user = \Auth::getLastAttempted();
            /** @noinspection PhpUndefinedFieldInspection */
            static::checkRole($user->id);
            if ($login) {
                /** @noinspection PhpUndefinedFieldInspection */
                $user->last_login_date = Carbon::now()->toDateTimeString();
                /** @noinspection PhpUndefinedFieldInspection */
                $user->confirm_code = 'y';
                /** @noinspection PhpUndefinedMethodInspection */
                $user->save();
                /** @noinspection PhpParamsInspection */
                Session::setUserInfoWithJWT($user, $remember, $appId);
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $userId
     *
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     */
    protected static function checkRole($userId)
    {
        $appId = static::get('app.id', null);

        if (!empty($appId) && !empty($userId)) {
            $roleId = UserAppRole::getRoleIdByAppIdAndUserId($appId, $userId);
            $roleInfo = ($roleId) ? Role::getCachedInfo($roleId) : null;
            if (!empty($roleInfo) && !array_get($roleInfo, 'is_active', false)) {
                throw new ForbiddenException('Role is not active.');
            }
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public static function logout()
    {
        $token = static::getSessionToken();
        if (empty($token)) {
            return false;
        }

        try {
            JWTUtilities::invalidate($token);
        } catch (\Exception $e) {
            if (!($e instanceof TokenBlacklistedException)) {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Sets basic info of the user in session with JWT when authenticated.
     *
     * @param  array|User $user
     * @param bool        $forever
     * @param integer     $appId
     *
     * @return bool
     */
    public static function setUserInfoWithJWT($user, $forever = false, $appId = null)
    {
        $userInfo = null;
        if ($user instanceof User) {
            $userInfo = $user->toArray();
            $userInfo['is_sys_admin'] = $user->is_sys_admin;
        }

        if (!empty($userInfo)) {
            $id = array_get($userInfo, 'id');
            $email = array_get($userInfo, 'email');
            $token = JWTUtilities::makeJWTByUser($id, $email, $forever);
            static::setSessionToken($token);

            if (!empty($appId) && !$user->is_sys_admin) {
                static::setSessionData($appId, $id);

                return true;
            } else {
                return static::setUserInfo($userInfo);
            }
        }

        return false;
    }

    /**
     * Sets basic info of the user in session when authenticated.
     *
     * @param array $user
     *
     * @return bool
     */
    public static function setUserInfo($user)
    {
        if (!empty($user)) {
            \Session::put('user.id', array_get($user, 'id'));
            \Session::put('user.name', array_get($user, 'name'));
            \Session::put('user.username', array_get($user, 'username'));
            \Session::put('user.display_name', array_get($user, 'name'));
            \Session::put('user.first_name', array_get($user, 'first_name'));
            \Session::put('user.last_name', array_get($user, 'last_name'));
            \Session::put('user.email', array_get($user, 'email'));
            \Session::put('user.is_sys_admin', array_get($user, 'is_sys_admin'));
            \Session::put('user.last_login_date', array_get($user, 'last_login_date'));

            return true;
        }

        return false;
    }

    /**
     * @param null $appId
     * @param null $userId
     */
    public static function setSessionData($appId = null, $userId = null)
    {
        $appInfo = ($appId) ? App::getCachedInfo($appId) : null;
        $userInfo = ($userId) ? User::getCachedInfo($userId) : null;

        $roleId = null;
        if (!empty($userId) && !empty($appId)) {
            $roleId = UserAppRole::getRoleIdByAppIdAndUserId($appId, $userId);
        }

        if (empty($roleId) && !empty($appInfo)) {
            $roleId = array_get($appInfo, 'role_id');
        }

        Session::setUserInfo($userInfo);
        Session::put('app.id', $appId);

        $roleInfo = ($roleId) ? Role::getCachedInfo($roleId) : null;
        if (!empty($roleInfo)) {
            Session::put('role.id', $roleId);
            Session::put('role.name', $roleInfo['name']);
            Session::put('role.services', $roleInfo['role_service_access_by_role_id']);
        }

        $systemLookup = Lookup::getCachedLookups();
        $systemLookup = (!empty($systemLookup)) ? $systemLookup : [];
        $appLookup = (!empty($appInfo['app_lookup_by_app_id'])) ? $appInfo['app_lookup_by_app_id'] : [];
        $roleLookup = (!empty($roleInfo['role_lookup_by_role_id'])) ? $roleInfo['role_lookup_by_role_id'] : [];
        $userLookup = (!empty($userInfo['user_lookup_by_user_id'])) ? $userInfo['user_lookup_by_user_id'] : [];

        $combinedLookup = static::combineLookups($systemLookup, $appLookup, $roleLookup, $userLookup);

        Session::put('lookup', array_get($combinedLookup, 'lookup'));
        //Actual values of the secret keys. For internal use only.
        Session::put('lookup_secret', array_get($combinedLookup, 'lookup_secret'));
    }

    /**
     * Fetches user session data based on the authenticated user.
     *
     * @return array
     * @throws UnauthorizedException
     */
    public static function getPublicInfo()
    {
        if (empty(session('user'))) {
            throw new UnauthorizedException('There is no valid session for the current request.');
        }

        $sessionData = [
            'session_token'   => session('session_token'),
            'session_id'      => session('session_token'), // temp for compatibility with 1.x
            'id'              => session('user.id'),
            'name'            => session('user.display_name'),
            'first_name'      => session('user.first_name'),
            'last_name'       => session('user.last_name'),
            'email'           => session('user.email'),
            'is_sys_admin'    => session('user.is_sys_admin'),
            'last_login_date' => session('user.last_login_date'),
            'host'            => gethostname()
        ];

        $role = static::get('role');
        if (!session('user.is_sys_admin') && !empty($role)) {
            $sessionData['role'] = array_get($role, 'name');
            $sessionData['role_id'] = array_get($role, 'id');
        }

        return $sessionData;
    }

    /**
     * @return User|null
     */
    public static function user()
    {
        if (static::isAuthenticated()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return User::find(static::getCurrentUserId());
        }

        return null;
    }

    /**
     * Gets user id of the currently logged in user.
     *
     * @return integer|null
     */
    public static function getCurrentUserId()
    {
        return session('user.id');
    }

    /**
     * Gets role id of the currently logged in user, if not admin.
     *
     * @return integer|null
     */
    public static function getRoleId()
    {
        return static::get('role.id');
    }

    /**
     * @return bool
     */
    public static function isAuthenticated()
    {
        $userId = static::getCurrentUserId();

        return boolval($userId);
    }

    /**
     * @return mixed
     */
    public static function getSessionToken()
    {
        return \Session::get('session_token');
    }

    /**
     * @param $token
     */
    public static function setSessionToken($token)
    {
        \Session::put('session_token', $token);
    }

    /**
     * @param $apiKey
     */
    public static function setApiKey($apiKey)
    {
        \Session::put('api_key', $apiKey);
    }

    /**
     * @return mixed
     */
    public static function getApiKey()
    {
        return \Session::get('api_key');
    }

    /**
     * @return bool
     */
    public static function isSysAdmin()
    {
        return boolval(session('user.is_sys_admin'));
    }

    public static function setRequestor($requestor = ServiceRequestorTypes::API)
    {
        \Session::put('requestor', $requestor);
    }

    public static function getRequestor()
    {
        return \Session::get('requestor', ServiceRequestorTypes::API);
    }

    public static function get($key, $default = null)
    {
        return \Session::get($key, $default);
    }

    public static function put($key, $value = null)
    {
        \Session::put($key, $value);
    }

    public static function push($key, $value)
    {
        \Session::push($key, $value);
    }

    public static function has($name)
    {
        return \Session::has($name);
    }

    public static function getId()
    {
        return \Session::getId();
    }

    public static function isValidId($id)
    {
        return \Session::isValidId($id);
    }

    public static function setId($sessionId)
    {
        \Session::setId($sessionId);
    }

    public static function start()
    {
        return \Session::start();
    }

    public static function driver($driver = null)
    {
        return \Session::driver($driver);
    }

    public static function all()
    {
        return \Session::all();
    }

    public static function flush()
    {
        \Session::flush();
    }

    public static function remove($name)
    {
        return \Session::remove($name);
    }

    public static function forget($key)
    {
        \Session::forget($key);
    }
}