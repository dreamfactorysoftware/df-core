<?php
namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ApiDocUtilities;

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
            Verbs::MERGE => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

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
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'security_question' => $user->security_question,
            'default_app_id'    => $user->default_app_id
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
            'first_name'        => ArrayUtils::get($payload, 'first_name'),
            'last_name'         => ArrayUtils::get($payload, 'last_name'),
            'name'              => ArrayUtils::get($payload, 'name'),
            'email'             => ArrayUtils::get($payload, 'email'),
            'phone'             => ArrayUtils::get($payload, 'phone'),
            'security_question' => ArrayUtils::get($payload, 'security_question'),
            'security_answer'   => ArrayUtils::get($payload, 'security_answer'),
            'default_app_id'    => ArrayUtils::get($payload, 'default_app_id')
        ];

        ArrayUtils::removeNull($data);

        $user = Session::user();

        if (empty($user)) {
            throw new NotFoundException('No user session found.');
        }

        $oldToken = Session::getSessionToken();
        $email = $user->email;
        $user->update($data);

        if (!empty($oldToken) && $email !== ArrayUtils::get($data, 'email', $email)) {
            // Email change invalidates token. Need to create a new token.
            $forever = JWTUtilities::isForever($oldToken);
            Session::setUserInfoWithJWT($user, $forever);
            $newToken = Session::getSessionToken();

            return ['success' => true, 'session_token' => $newToken];
        }

        return ['success' => true];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getProfile() - Retrieve the current user\'s profile information.',
                        'nickname'         => 'getProfile',
                        'type'             => 'ProfileResponse',
                        'event_name'       => $eventPath . '.read',
                        'responseMessages' => ApiDocUtilities::getCommonResponses([401, 500]),
                        'notes'            =>
                            'A valid current session is required to use this API. ' .
                            'This profile, along with password, is the only things that the user can directly change.',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'updateProfile() - Update the current user\'s profile information.',
                        'nickname'         => 'updateProfile',
                        'type'             => 'Success',
                        'event_name'       => $eventPath . '.update',
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs for the user profile.',
                                'allowMultiple' => false,
                                'type'          => 'ProfileRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'Update the display name, phone, etc., as well as, security question and answer.',
                    ],
                ],
                'description' => 'Operations on a user\'s profile.',
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
                'id'         => 'ProfileRequest',
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
                'id'         => 'ProfileResponse',
                'properties' => $commonProfile,
            ],
        ];

        return ['apis' => $apis, 'models' => $models];
    }
}