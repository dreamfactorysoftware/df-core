<?php
namespace DreamFactory\Core\Http\Middleware;

use \Auth;
use \Cache;
use \Config;
use \Closure;
use DreamFactory\Core\Utility\LookupKey;
use Illuminate\Routing\Router;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\CacheUtilities;

class AccessCheck
{
    protected static $exceptions = [
        [
            'verb_mask' => 31, //Allow all verbs
            'service'   => 'system',
            'resource'  => 'admin/session'
        ],
        [
            'verb_mask' => 31, //Allow all verbs
            'service'   => 'user',
            'resource'  => 'session'
        ],
        [
            'verb_mask' => 2, //Allow POST only
            'service'   => 'user',
            'resource'  => 'password'
        ],
        [
            'verb_mask' => 2, //Allow POST only
            'service'   => 'system',
            'resource'  => 'admin/password'
        ],
        [
            'verb_mask' => 1,
            'service'   => 'system',
            'resource'  => 'environment'
        ]
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //user.temp is set to true only when authenticated using basic auth.
        //Since we are not using laravel's Auth::Check() method to check for authenticated
        //user the Auth::onceBasic() method doesn't do the job. Therefore, user.temp is set to true
        //when user authenticates using basic auth. Then on the following request the session is
        //flushed away based on user.temp flag.
        if(true === Session::get('user.temp'))
        {
            Session::flush();
        }

        if (!Session::isAuthenticated()) {
            //If user is not authenticated.

            //Check to see if session ID is supplied for using an existing session.
            $sessionId = $request->header('X_DREAMFACTORY_SESSION_TOKEN');

            if (!empty($sessionId) && !Auth::check()) {
                if (Session::isValidId($sessionId)) {
                    Session::setId($sessionId);
                    Session::start();
                    \Request::setSession(Session::driver());
                }
            }

            //If still no authenticated user then try basic auth.
            if (!Session::isAuthenticated()) {
                Auth::onceBasic();
                /** @var User $authenticatedUser */
                $authenticatedUser = Auth::user();
                if (!empty($authenticatedUser)) {
                    Session::setUserInfo($authenticatedUser, true);
                }
            }
        }

        //Check for API key in request parameters.
        $apiKey = $request->query('api_key');
        if (empty($apiKey)) {
            //Check for API key in request HEADER.
            $apiKey = $request->header('X_DREAMFACTORY_API_KEY');
        }

        //Storing this in session to be able to easily look it up. Otherwise would have to lookup it up from Request object.
        Session::setCurrentApiKey($apiKey);
        //ID of the currently authenticated user.
        $userId = Session::getCurrentUserId();

        if (Session::isAuthenticated() && Session::isSysAdmin()) {
            //If authenticated and user is a system admin then all is allowed for system admin.
            $appId = null;
            if ($apiKey && !Session::hasApiKey($apiKey)) {
                $cacheKey = CacheUtilities::makeApiKeyUserIdKey($apiKey, $userId);
                $cacheData = (!empty($cacheData)) ? Cache::get($cacheKey) : [];
                $appId = ArrayUtils::get($cacheData, 'app_id');

                if (empty($appId)) {
                    $app = App::whereApiKey($apiKey)->first();
                    $appId = $app->id;
                    $cacheData = [
                        'user_id' => $userId,
                        'app_id'  => $app->id
                    ];
                    Cache::put($cacheKey, $cacheData, Config::get('df.default_cache_ttl'));
                }

                Session::setLookupKeys($apiKey, null, $appId, $userId);
            } elseif (!Session::has('admin')) {
                $lookup = LookupKey::getLookup(null, $appId, $userId);
                Session::put('admin.lookup', ArrayUtils::get($lookup, 'lookup', []));
                Session::put('admin.lookup_secret', ArrayUtils::get($lookup, 'lookup_secret', []));
            }
        } else if (!empty($apiKey) &&
            Session::isAuthenticated() &&
            class_exists('\DreamFactory\Core\User\Resources\System\User')
        ) {
            //If API key is provided and authenticated user is non-admin and user management package is installed.
            //Use the role assigned to this user for the app.
            if (!Session::hasApiKey($apiKey)) {
                $cacheKey = CacheUtilities::makeApiKeyUserIdKey($apiKey, $userId);
                $cacheData = Cache::get($cacheKey);
                $roleData = (!empty($cacheData)) ? ArrayUtils::get($cacheData, 'role_data') : [];

                if (empty($roleData)) {
                    /** @var App $app */
                    $app = App::with(
                        [
                            'role_by_user_to_app_to_role' => function ($q) use ($userId){
                                $q->whereUserId($userId);
                            }
                        ]
                    )->whereApiKey($apiKey)->first();

                    if (empty($app)) {
                        return static::getException(new UnauthorizedException('Unauthorized request. Invalid API Key.'),
                            $request);
                    }

                    /** @var Role $role */
                    $role = $app->getRelation('role_by_user_to_app_to_role')->first();

                    if (empty($role)) {
                        $app->load('role_by_role_id');
                        /** @var Role $role */
                        $role = $app->getRelation('role_by_role_id');
                    }

                    if (empty($role)) {
                        return static::getException(
                            new InternalServerErrorException('Unexpected error occurred. Role not found for Application.'),
                            $request
                        );
                    }

                    $roleData = static::getRoleData($role);
                    $cacheData = [
                        'role_data' => $roleData,
                        'user_id'   => $userId,
                        'app_id'    => $app->id
                    ];
                    Cache::put($cacheKey, $cacheData, Config::get('df.default_cache_ttl'));
                }

                Session::putWithApiKey($apiKey, 'role', $roleData);
                Session::setLookupKeys(
                    $apiKey,
                    ArrayUtils::get($roleData, 'id'),
                    ArrayUtils::get($cacheData, 'app_id'),
                    ArrayUtils::get($cacheData, 'user_id')
                );
            }
        } elseif (!empty($apiKey)) {
            //If no user is authenticated but API key is provided. Use the default role of this app.
            if (!Session::hasApiKey($apiKey)) {
                $cacheKey = CacheUtilities::makeApiKeyUserIdKey($apiKey);
                $cacheData = Cache::get($cacheKey);
                $roleData = (!empty($cacheData)) ? ArrayUtils::get($cacheData, 'role_data') : [];

                if (empty($roleData)) {
                    /** @var App $app */
                    $app = App::with('role_by_role_id')->whereApiKey($apiKey)->first();

                    if (empty($app)) {
                        return static::getException(new UnauthorizedException('Unauthorized request. Invalid API Key.'),
                            $request);
                    }

                    /** @var Role $role */
                    $role = $app->getRelation('role_by_role_id');

                    if (empty($role)) {
                        return static::getException(
                            new InternalServerErrorException('Unexpected error occurred. Role not found for Application.'),
                            $request
                        );
                    }

                    $roleData = static::getRoleData($role);
                    $cacheData = [
                        'role_data' => $roleData,
                        'app_id'    => $app->id
                    ];
                    Cache::put($cacheKey, $cacheData, Config::get('df.default_cache_ttl'));
                }

                Session::putWithApiKey($apiKey, 'role', $roleData);
                Session::setLookupKeys($apiKey, ArrayUtils::get($roleData, 'id'),
                    ArrayUtils::get($cacheData, 'app_id'));
            }
        } elseif (static::isException()) {
            //If no API key and user is non-admin then check for exception cases.
            return $next($request);
        } else {
            //No Api key provided, user is not an admin, and is not an exception case. Throws exception.
            $basicAuthUser = $request->getUser();
            if (!empty($basicAuthUser)) {
                return static::getException(new UnauthorizedException('Unauthorized. User credentials did not match.'),
                    $request);
            }

            return static::getException(new BadRequestException('Bad request. Missing API key.'), $request);
        }

        if (Session::isAccessAllowed()) {
            return $next($request);
        } elseif (static::isException()) {
            //API key and/or (non-admin) user logged in, but if access is still not allowed then check for exception case.

            return $next($request);
        } else {
            return static::getException(new ForbiddenException('Access Forbidden.'), $request);
        }
    }

    /**
     * @param \Exception               $e
     * @param \Illuminate\Http\Request $request
     *
     * @return array|mixed|string
     */
    protected static function getException($e, $request)
    {
        $response = ResponseFactory::create($e);

        $accepts = explode(',', $request->header('ACCEPT'));

        return ResponseFactory::sendResponse($response, $accepts);
    }

    /**
     * Generates the role data array using the role model.
     *
     * @param Role $role
     *
     * @return array
     */
    protected static function getRoleData(Role $role)
    {
        $rsa = $role->getRoleServiceAccess();

        $roleData = [
            'name'     => $role->name,
            'id'       => $role->id,
            'services' => $rsa
        ];

        return $roleData;
    }

    /**
     * Checks to see if it is an admin user login call.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    protected static function isException()
    {
        /** @var Router $router */
        $router = app('router');
        $service = strtolower($router->input('service'));
        $resource = strtolower($router->input('resource'));
        $action = VerbsMask::toNumeric(\Request::getMethod());

        foreach (static::$exceptions as $exception) {
            if (($action & ArrayUtils::get($exception, 'verb_mask')) &&
                $service === ArrayUtils::get($exception, 'service') &&
                $resource === ArrayUtils::get($exception, 'resource')
            ) {
                return true;
            }
        }

        return false;
    }
}