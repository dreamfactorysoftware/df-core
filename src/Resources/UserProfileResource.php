<?php
namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ArrayUtils;
use DreamFactory\Core\Enums\Verbs;

class UserProfileResource extends BaseRestResource
{
    const RESOURCE_NAME = 'profile';

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        $settings["verbAliases"] = $verbAliases;

        parent::__construct($settings);
    }

    /**
     * Fetches user profile.
     *
     * @return array
     * @throws UnauthorizedException
     */
    protected function handleGET()
    {
        $user = Session::user();

        if (empty($user)) {
            throw new UnauthorizedException('There is no valid session for the current request.');
        }

        $data = [
            'username'          => $user->username,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'security_question' => $user->security_question,
            'default_app_id'    => $user->default_app_id,
            'oauth_provider'    => (!empty($user->oauth_provider))? $user->oauth_provider : '',
            'adldap'            => (!empty($user->adldap))? $user->adldap : ''
        ];

        return $data;
    }

    /**
     * Updates user profile.
     *
     * @return array
     * @throws NotFoundException
     * @throws \Exception
     */
    protected function handlePOST()
    {
        $payload = $this->getPayloadData();

        $data = [
            'username'          => array_get($payload, 'username'),
            'first_name'        => array_get($payload, 'first_name'),
            'last_name'         => array_get($payload, 'last_name'),
            'name'              => array_get($payload, 'name'),
            'email'             => array_get($payload, 'email'),
            'phone'             => array_get($payload, 'phone'),
            'security_question' => array_get($payload, 'security_question'),
            'security_answer'   => array_get($payload, 'security_answer'),
            'default_app_id'    => array_get($payload, 'default_app_id')
        ];

        ArrayUtils::removeNull($data);

        $user = Session::user();

        if (empty($user)) {
            throw new NotFoundException('No user session found.');
        }

        $oldToken = Session::getSessionToken();
        $email = $user->email;
        $user->update($data);

        if (!empty($oldToken) && $email !== array_get($data, 'email', $email)) {
            // Email change invalidates token. Need to create a new token.
            $forever = JWTUtilities::isForever($oldToken);
            Session::setUserInfoWithJWT($user, $forever);
            $newToken = Session::getSessionToken();

            return ['success' => true, 'session_token' => $newToken];
        }

        return ['success' => true];
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = camel_case($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;

        $apis = [
            $path => [
                'get'  => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' .
                        $capitalized .
                        'Profile() - Retrieve the current user\'s profile information.',
                    'operationId'       => 'get' . $capitalized . 'Profile',
                    'responses'         => [
                        '200'     => [
                            'description' => 'Profile',
                            'schema'      => ['$ref' => '#/definitions/ProfileResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       =>
                        'A valid current session is required to use this API. ' .
                        'This profile, along with password, is the only things that the user can directly change.',
                ],
                'post' => [
                    'tags'              => [$serviceName],
                    'summary'           => 'updateProfile() - Update the current user\'s profile information.',
                    'operationId'       => 'updateProfile',
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs for the user profile.',
                            'schema'      => ['$ref' => '#/definitions/ProfileRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'Update the display name, phone, etc., as well as, security question and answer.',
                ],
            ],
        ];

        $commonProfile = [
            'email'             => [
                'type'        => 'string',
                'description' => 'Email address of the current user.',
            ],
            'first_name'        => [
                'type'        => 'string',
                'description' => 'First name of the current user.',
            ],
            'last_name'         => [
                'type'        => 'string',
                'description' => 'Last name of the current user.',
            ],
            'display_name'      => [
                'type'        => 'string',
                'description' => 'Full display name of the current user.',
            ],
            'phone'             => [
                'type'        => 'string',
                'description' => 'Phone number.',
            ],
            'security_question' => [
                'type'        => 'string',
                'description' => 'Question to be answered to initiate password reset.',
            ],
            'default_app_id'    => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Id of the application to be launched at login.',
            ],
        ];

        $models = [
            'ProfileRequest'  => [
                'type'       => 'object',
                'properties' => array_merge(
                    $commonProfile,
                    [
                        'security_answer' => [
                            'type'        => 'string',
                            'description' => 'Answer to the security question.',
                        ],
                    ]
                ),
            ],
            'ProfileResponse' => [
                'type'       => 'object',
                'properties' => $commonProfile,
            ],
        ];

        return ['paths' => $apis, 'definitions' => $models];
    }
}