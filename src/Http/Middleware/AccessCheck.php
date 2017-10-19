<?php

namespace DreamFactory\Core\Http\Middleware;

use Closure;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Enums\Verbs;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use ServiceManager;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class AccessCheck
{
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return array|mixed|string
     */
    public function handle($request, Closure $next)
    {
        //  Allow console requests through
        if (env('DF_IS_VALID_CONSOLE_REQUEST', false)) {
            return $next($request);
        }

        try {
            if (Session::getBool('token_expired')) {
                throw new UnauthorizedException(Session::get('token_expired_msg'));
            } elseif (Session::getBool('token_blacklisted')) {
                throw new ForbiddenException(Session::get('token_blacklisted_msg'));
            } elseif (Session::getBool('token_invalid')) {
                throw new UnauthorizedException(Session::get('token_invalid_msg'));
            }

            if (static::isAccessAllowed()) {
                return $next($request);
            } else {
                // No access allowed, figure out the best error response
                $apiKey = Session::getApiKey();
                $token = Session::getSessionToken();
                $roleId = Session::getRoleId();
                $callFromLocalScript = (ServiceRequestorTypes::SCRIPT == Session::getRequestor());

                if (!$callFromLocalScript && empty($apiKey) && empty($token)) {
                    $msg = 'No session token (JWT) or API Key detected in request. ' .
                        'Please send in X-DreamFactory-Session-Token and/or X-Dreamfactory-API-Key request header. ' .
                        'You can also use URL query parameters session_token and/or api_key.';
                    throw new BadRequestException($msg);
                } elseif (empty($roleId)) {
                    if (empty($apiKey)) {
                        throw new BadRequestException(
                            "No API Key provided. Please provide a valid API Key using X-Dreamfactory-API-Key request header or 'api_key' url query parameter."
                        );
                    } elseif (empty($token)) {
                        throw new BadRequestException(
                            "No session token (JWT) provided. Please provide a valid JWT using X-DreamFactory-Session-Token request header or 'session_token' url query parameter."
                        );
                    } else {
                        throw new ForbiddenException(
                            "Role not found. A Role may not be assigned to you for your App."
                        );
                    }
                } elseif (!Role::getCachedInfo($roleId, 'is_active')) {
                    throw new ForbiddenException("Access Forbidden. Role assigned to you for you App or the default role of your App is not active.");
                } elseif (!Session::isAuthenticated()) {
                    throw new UnauthorizedException('Unauthorized. User is not authenticated.');
                } else {
                    $msg = 'Access Forbidden. You do not have enough privileges to access this resource. ';
                    try {
                        // Try to get a detail error message if possible.
                        $router = app('router');
                        $service = strtolower($router->input('service'));
                        $component = trim(strtolower($router->input('resource')), '/');
                        $requestor = Session::getRequestor();
                        Session::checkServicePermission(\Request::getMethod(), $service, $component, $requestor);
                    } catch (ForbiddenException $e) {
                        $msg = 'Access Forbidden. ' . $e->getMessage();
                        $e->setMessage($msg);
                        throw $e;
                    }
                    throw new ForbiddenException($msg);
                }
            }
        } catch (\Exception $e) {
            return ResponseFactory::sendException($e, $request);
        }
    }

    /**
     * Checks to see if Access is Allowed based on Role-Service-Access.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public static function isAccessAllowed()
    {
        if (!in_array($method = \Request::getMethod(), Verbs::getDefinedConstants())) {
            throw new MethodNotAllowedHttpException(Verbs::getDefinedConstants(),
                "Invalid verb tunneling with $method");
        }

        /** @var Router $router */
        $router = app('router');
        $service = strtolower($router->input('service'));
        $component = strtolower($router->input('resource'));
        $requestor = Session::getRequestor();
        $allowed = Session::getServicePermissions($service, $component, $requestor);
        $action = VerbsMask::toNumeric($method);

        if ($action & $allowed) {
            return true;
        } else {
            if (empty($service)) {
                return true; // root of api gives available service listing
            }

            if (ServiceManager::isAccessException($service, $component, $action)) {
                return true;
            }
        }

        return false;
    }
}
