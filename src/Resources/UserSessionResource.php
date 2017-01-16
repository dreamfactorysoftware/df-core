<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\ADLdap\Services\ADLdap;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\OAuth\Services\BaseOAuthService;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Inflector;
use ServiceManager;

class UserSessionResource extends BaseRestResource
{
    const RESOURCE_NAME = 'session';

    /**
     * Gets basic user session data and performs OAuth login redirect.
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    protected function handleGET()
    {
        $serviceName = $this->getOAuthServiceName();
        if (!empty($serviceName)) {
            /** @type BaseOAuthService $service */
            $service = ServiceManager::getService($serviceName);
            $serviceGroup = $service->getServiceTypeInfo()->getGroup();

            if ($serviceGroup !== ServiceTypeGroups::OAUTH) {
                throw new BadRequestException('Invalid login service provided. Please use an OAuth service.');
            }

            return $service->handleLogin($this->request->getDriver());
        }

        return Session::getPublicInfo();
    }

    /**
     * Authenticates valid user.
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    protected function handlePOST()
    {
        $serviceName = $this->getOAuthServiceName();

        if (empty($serviceName)) {
            $credentials = [
                'email'        => $this->getPayloadData('email'),
                'password'     => $this->getPayloadData('password'),
                'is_sys_admin' => false
            ];

            return $this->handleLogin($credentials, boolval($this->getPayloadData('remember_me')));
        }

        $service = ServiceManager::getService($serviceName);
        $serviceGroup = $service->getServiceTypeInfo()->getGroup();

        switch ($serviceGroup) {
            case ServiceTypeGroups::LDAP:
                $credentials = [
                    'username' => $this->getPayloadData('username'),
                    'password' => $this->getPayloadData('password')
                ];

                /** @type ADLdap $service */
                return $service->handleLogin($credentials, $this->getPayloadData('remember_me'));
            case ServiceTypeGroups::OAUTH:
                $oauthCallback = $this->request->getParameterAsBool('oauth_callback');

                /** @type BaseOAuthService $service */
                if (!empty($oauthCallback)) {
                    return $service->handleOAuthCallback();
                } else {
                    return $service->handleLogin($this->request->getDriver());
                }
            default:
                throw new BadRequestException('Invalid login service provided. Please use an OAuth or AD/Ldap service.');
        }
    }

    /**
     * Retrieves OAuth service name from request param, payload, or using state identifier.
     *
     * @return mixed
     */
    protected function getOAuthServiceName()
    {
        $serviceName = $this->getPayloadData('service', $this->request->getParameter('service'));

        if (empty($serviceName)) {
            $state = $this->getPayloadData('state', $this->request->getParameter('state'));
            if (empty($state)) {
                $state = $this->getPayloadData('oauth_token', $this->request->getParameter('oauth_token'));
            }
            if (!empty($state)) {
                $key = BaseOAuthService::CACHE_KEY_PREFIX . $state;
                $serviceName = \Cache::pull($key);
            }
        }

        return $serviceName;
    }

    /**
     * Refreshes current JWT.
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    protected function handlePUT()
    {
        JWTUtilities::refreshToken();

        return Session::getPublicInfo();
    }

    /**
     * Logs out user
     *
     * @return array
     */
    protected function handleDELETE()
    {
        Session::logout();

        //Clear everything in session.
        Session::flush();

        return ['success' => true];
    }

    /**
     * {@inheritdoc}
     */
    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $apis = [
            $path => [
                'get'    => [
                    'tags'        => [$serviceName],
                    'summary'     => 'get' .
                        $capitalized .
                        'Session() - Retrieve the current user session information.',
                    'operationId' => 'getSession' . $capitalized,
                    'responses'   => [
                        '200'     => [
                            'description' => 'Session',
                            'schema'      => ['$ref' => '#/definitions/Session']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Calling this refreshes the current session, or returns an error for timed-out or invalid sessions.',
                ],
                'post'   => [
                    'tags'        => [$serviceName],
                    'summary'     => 'login' . $capitalized . '() - Login and create a new user session.',
                    'operationId' => 'login' . $capitalized,
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs used for logging into the system.',
                            'schema'      => ['$ref' => '#/definitions/Login'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Session',
                            'schema'      => ['$ref' => '#/definitions/Session']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Calling this creates a new session and logs in the user.',
                ],
                'delete' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'logout' .
                        $capitalized .
                        '() - Logout and destroy the current user session.',
                    'operationId' => 'logout' . $capitalized,
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Calling this deletes the current session and logs out the user.',
                ],
            ],
        ];

        $models = [
            'Session'    => [
                'type'       => 'object',
                'properties' => [
                    'id'              => [
                        'type'        => 'string',
                        'description' => 'Identifier for the current user.',
                    ],
                    'email'           => [
                        'type'        => 'string',
                        'description' => 'Email address of the current user.',
                    ],
                    'first_name'      => [
                        'type'        => 'string',
                        'description' => 'First name of the current user.',
                    ],
                    'last_name'       => [
                        'type'        => 'string',
                        'description' => 'Last name of the current user.',
                    ],
                    'display_name'    => [
                        'type'        => 'string',
                        'description' => 'Full display name of the current user.',
                    ],
                    'is_sys_admin'    => [
                        'type'        => 'boolean',
                        'description' => 'Is the current user a system administrator.',
                    ],
                    'role'            => [
                        'type'        => 'string',
                        'description' => 'Name of the role to which the current user is assigned.',
                    ],
                    'last_login_date' => [
                        'type'        => 'string',
                        'description' => 'Date timestamp of the last login for the current user.',
                    ],
                    'app_groups'      => [
                        'type'        => 'array',
                        'description' => 'App groups and the containing apps.',
                        'items'       => [
                            '$ref' => '#/definitions/SessionApp',
                        ],
                    ],
                    'no_group_apps'   => [
                        'type'        => 'array',
                        'description' => 'Apps that are not in any app groups.',
                        'items'       => [
                            '$ref' => '#/definitions/SessionApp',
                        ],
                    ],
                    'session_id'      => [
                        'type'        => 'string',
                        'description' => 'Id for the current session, used in X-DreamFactory-Session-Token header for API requests.',
                    ],
                    'ticket'          => [
                        'type'        => 'string',
                        'description' => 'Timed ticket that can be used to start a separate session.',
                    ],
                    'ticket_expiry'   => [
                        'type'        => 'string',
                        'description' => 'Expiration time for the given ticket.',
                    ],
                ],
            ],
            'Login'      => [
                'type'       => 'object',
                'required'   => ['email', 'password'],
                'properties' => [
                    'email'    => [
                        'type' => 'string'
                    ],
                    'password' => [
                        'type' => 'string'
                    ],
                    'duration' => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Duration of the session, Defaults to 0, which means until browser is closed.',
                    ],
                ],
            ],
            'SessionApp' => [
                'type'       => 'object',
                'properties' => [
                    'id'                      => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Id of the application.',
                    ],
                    'name'                    => [
                        'type'        => 'string',
                        'description' => 'Displayed name of the application.',
                    ],
                    'description'             => [
                        'type'        => 'string',
                        'description' => 'Description of the application.',
                    ],
                    'is_url_external'         => [
                        'type'        => 'boolean',
                        'description' => 'Does this application exist on a separate server.',
                    ],
                    'launch_url'              => [
                        'type'        => 'string',
                        'description' => 'URL at which this app can be accessed.',
                    ],
                    'requires_fullscreen'     => [
                        'type'        => 'boolean',
                        'description' => 'True if the application requires fullscreen to run.',
                    ],
                    'allow_fullscreen_toggle' => [
                        'type'        => 'boolean',
                        'description' => 'True allows the fullscreen toggle widget to be displayed.',
                    ],
                    'toggle_location'         => [
                        'type'        => 'string',
                        'description' => 'Where the fullscreen toggle widget is to be displayed, defaults to top.',
                    ],
                    'is_default'              => [
                        'type'        => 'boolean',
                        'description' => 'True if this app is set to launch by default at sign in.',
                    ],
                ],
            ],
        ];

        return ['paths' => $apis, 'definitions' => $models];
    }

    /**
     * Performs login.
     *
     * @param array $credentials
     * @param bool  $remember
     *
     * @return array
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \Exception
     */
    protected function handleLogin(array $credentials = [], $remember = false)
    {
        $email = array_get($credentials, 'email');
        if (empty($email)) {
            throw new BadRequestException('Login request is missing required email.');
        }

        $password = array_get($credentials, 'password');
        if (empty($password)) {
            throw new BadRequestException('Login request is missing required password.');
        }

        $credentials['is_active'] = 1;

        // if user management not available then only system admins can login.
        if (!class_exists('\DreamFactory\Core\User\Resources\System\User')) {
            $credentials['is_sys_admin'] = 1;
        }

        if (Session::authenticate($credentials, $remember, true, $this->getAppId())) {
            return Session::getPublicInfo();
        } else {
            throw new UnauthorizedException('Invalid credentials supplied.');
        }
    }

    /**
     * @return int|null
     */
    protected function getAppId()
    {
        //Check for API key in request parameters.
        $apiKey = $this->request->getApiKey();

        if (!empty($apiKey)) {
            return App::getAppIdByApiKey($apiKey);
        }

        return null;
    }
}