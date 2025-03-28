<?php

namespace DreamFactory\Core\Http\Middleware;

use Closure;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Enums\Verbs;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
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
        if (!in_array($method = \Request::getMethod(), Verbs::getDefinedConstants())) {
            throw new MethodNotAllowedHttpException(Verbs::getDefinedConstants(),
                "Invalid or unsupported verb '$method' used in request.");
        }

        //  Allow console requests through
        if (env('DF_IS_VALID_CONSOLE_REQUEST', false)) {
            return $next($request);
        }

        try {
            // if the request provides a JWT and it is bad, throw reason first
            if (Session::getBool('token_expired')) {
                throw new UnauthorizedException(Session::get('token_expired_msg'));
            } elseif (Session::getBool('token_blacklisted')) {
                throw new ForbiddenException(Session::get('token_blacklisted_msg'));
            } elseif (Session::getBool('token_invalid')) {
                throw new UnauthorizedException(Session::get('token_invalid_msg'));
            }

            /** @var Router $router */
            $router = app('router');
            // root of API gives available service listing
            if (empty($service = strtolower($router->input('service')))) {
                return $next($request);
            }

            $component = strtolower($router->input('resource'));
            $requestor = Session::getRequestor();
            $permException = null;
            try {
                if ($this->isOAuthService($service)){
                    return $next($request);
                }
                Session::checkServicePermission($method, $service, $component, $requestor);

                return $next($request);
            } catch (RestException $e) {

                $permException = $e;
            }

            // No access allowed, figure out the best error response
            $apiKey = Session::getApiKey();
            $token = Session::getSessionToken();
            $roleId = Session::getRoleId();
            $callFromLocalScript = (ServiceRequestorTypes::SCRIPT == $requestor);

            if (!$callFromLocalScript && empty($apiKey) && empty($token)) {
                $msg = 'No session token (JWT) or API Key detected in request. ' .
                    'Please send in X-DreamFactory-Session-Token and/or X-DreamFactory-API-Key request header. ' .
                    'You can also use URL query parameters session_token and/or api_key.';
                throw new BadRequestException($msg);
            } elseif (empty($roleId)) {
                if (empty($apiKey)) {
                    throw new BadRequestException(
                        "No API Key provided. Please provide a valid API Key using X-DreamFactory-API-Key request header or 'api_key' url query parameter."
                    );
                } elseif (empty($token)) {
                    throw new BadRequestException(
                        "No session token (JWT) provided. Please provide a valid JWT using X-DreamFactory-Session-Token request header or 'session_token' url query parameter."
                    );
                } else {
                    throw new ForbiddenException(
                        "Role not found. A role may not be assigned to you or your application."
                    );
                }
            } elseif (!Role::getCachedInfo($roleId, 'is_active')) {
                throw new ForbiddenException("Access Forbidden. The role assigned to you or your application or the default role of your application is not active.");
            } elseif (!Session::isAuthenticated()) {
                throw new UnauthorizedException('Unauthorized. User is not authenticated.');
            } else {
                if ($permException) {
                    // Try to get a detail error message if possible.
                    $permException->setMessage('Access Forbidden. ' . $permException->getMessage());
                    throw $permException;
                }
                $msg = 'Access Forbidden. You do not have enough privileges to access this resource.';
                throw new ForbiddenException($msg);
            }
        } catch (\Exception $e) {
            return ResponseFactory::sendException($e);
        }
    }

    private function isOAuthService($service)
    {
        return str_ends_with($service, '_oauth');
    }
}
