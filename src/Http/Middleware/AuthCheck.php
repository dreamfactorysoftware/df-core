<?php

namespace DreamFactory\Core\Http\Middleware;

use Auth;
use Cache;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Enums\Verbs;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Payload;

class AuthCheck
{
    /**
     * APIs used for authentication
     * @var array
     */
    protected static $authApis = [
        'api/v2/user/session',
        'api/v2/system/admin/session'
    ];

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public static function getApiKey($request)
    {
        // Check for API key in request parameters.
        $apiKey = $request->query('api_key');
        if (empty($apiKey)) {
            // Check for API key in request HEADER.
            $apiKey = $request->header('X_DREAMFACTORY_API_KEY');
        }
        if (empty($apiKey)) {
            // Check for API key in request payload.
            // Skip if this is a call for system/app
            $route = $request->getPathInfo();
            if (strpos($route, 'system/app') === false) {
                $apiKey = $request->input('api_key');
            }
        }

        return $apiKey;
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public static function getJwt($request)
    {
        $token = static::getJWTFromAuthHeader();
        if (empty($token)) {
            $token = $request->header('X_DREAMFACTORY_SESSION_TOKEN');
        }
        if (empty($token)) {
            $token = $request->input('session_token');
        }

        return $token;
    }

    /**
     * Gets the token from Authorization header.
     *
     * @return string
     */
    protected static function getJWTFromAuthHeader()
    {
        if ('testing' === env('APP_ENV')) {
            // getallheaders method is not available in unit test mode.
            return [];
        }

        if (!function_exists('getallheaders')) {
            function getallheaders()
            {
                if (!is_array($_SERVER)) {
                    return [];
                }

                $headers = [];
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] =
                            $value;
                    }
                }

                return $headers;
            }
        }

        $token = null;
        $headers = getallheaders();
        $authHeader = array_get($headers, 'Authorization');
        if (strpos($authHeader, 'Bearer') !== false) {
            $token = substr($authHeader, 7);
        }

        return $token;
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public static function getScriptToken($request)
    {
        // Check for script authorizing token in request parameters.
        $token = $request->query('script_token');
        if (empty($token)) {
            // Check for script token in request HEADER.
            $token = $request->header('X_DREAMFACTORY_SCRIPT_TOKEN');
        }
        if (empty($token)) {
            // Check for script token in request payload.
            $token = $request->input('script_token');
        }

        return $token;
    }

    /**
     * @param Request  $request
     * @param \Closure $next
     *
     * @return array|mixed|string
     */
    public function handle(Request $request, \Closure $next)
    {
        // Not using any stateful session. Therefore, no need to track session
        // using cookies. Disabling tracking session by browser cookies.
        ini_set('session.use_cookies', 0);
        if (!in_array($route = $request->getPathInfo(), ['/setup', '/setup_db',])) {
            try {
                // Get the API key
                $apiKey = static::getApiKey($request);
                Session::setApiKey($apiKey);
                $appId = App::getAppIdByApiKey($apiKey);

                // Get the session token (JWT)
                $token = static::getJwt($request);
                if (in_array(trim($route, '/'), static::$authApis) && $request->getMethod() === Verbs::POST) {
                    // If this is a request for login ignore any token provided.
                    $token = null;
                }
                Session::setSessionToken($token);

                // Get the script token
                if (!empty($scriptToken = static::getScriptToken($request))) {
                    Session::setRequestor(ServiceRequestorTypes::SCRIPT);
                }

                // Check for basic auth attempt and valid JWT

                if (!empty($token)) {
                    /**
                     * Note: All caught exceptions from JWT are stored in session variables.
                     * These are later checked and handled appropriately in the AccessCheck middleware.
                     *
                     * This is to allow processing API calls that do not require any valid
                     * authenticated session. For example POST user/session to login,
                     * PUT user/session to refresh old JWT, GET system/environment etc.
                     *
                     * This also allows for auditing API calls that are called by not permitted/processed.
                     * It also allows counting unauthorized API calls against API limits.
                     */

                    if (in_array(trim($route, '/'), static::$authApis) && $request->getMethod() === Verbs::PUT) {
                        /*
                         * If this is a request for refresh token ignore token validity checking which runs in JWTAuth::getPayload() method.
                         * Token expired and going to be refreshed.                         *
                         * */
                    } else {
                        try {
                            JWTAuth::setToken($token);
                            /** @type Payload $payload */
                            $payload = JWTAuth::getPayload();
                            JWTUtilities::verifyUser($payload);
                            $userId = $payload->get('user_id');
                            Session::setSessionData($appId, $userId);
                        } catch (TokenExpiredException $e) {
                            JWTUtilities::clearAllExpiredTokenMaps();
                            Session::put('token_expired', true);
                            Session::put(
                                'token_expired_msg', $e->getMessage() .
                                ': Session expired. Please refresh your token (if still within refresh window) or re-login.'
                            );
                        } catch (TokenBlacklistedException $e) {
                            Session::put('token_blacklisted', true);
                            Session::put(
                                'token_blacklisted_msg',
                                $e->getMessage() . ': Session terminated. Please re-login.'
                            );
                        } catch (TokenInvalidException $e) {
                            Session::put('token_invalid', true);
                            Session::put('token_invalid_msg', 'Invalid token: ' . $e->getMessage());
                        }
                    }
                } elseif (!empty($basicAuthUser = $request->getUser()) ||
                    !empty($basicAuthPassword = $request->getPassword())) {
                    // Attempting to login using basic auth.
                    Auth::onceBasic();
                    /** @var User $authenticatedUser */
                    $authenticatedUser = Auth::user();
                    if (!empty($authenticatedUser)) {
                        $userId = $authenticatedUser->id;
                        Session::setSessionData($appId, $userId);
                    } else {
                        throw new UnauthorizedException('Unauthorized. User credentials did not match.');
                    }
                } elseif (!empty($scriptToken)) {
                    // keep this separate from basic auth and jwt handling,
                    // as this is the fall back when those are not provided from scripting (see node.js and python)
                    if ($temp = Cache::get('script-token:' . $scriptToken)) {
                        \Log::debug('script token: ' . $scriptToken);
                        \Log::debug('script token cache: ' . print_r($temp, true));
                        Session::setSessionData(array_get($temp, 'app_id'), array_get($temp, 'user_id'));
                    }
                } elseif (!empty($appId)) {
                    //Just Api Key is supplied. No authenticated session
                    Session::setSessionData($appId);
                }

                return $next($request);
            } catch (\Exception $e) {
                return ResponseFactory::sendException($e);
            }
        }

        return $next($request);
    }
}