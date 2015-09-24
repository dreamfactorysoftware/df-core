<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Curl;

class RegisterContact
{
    /**
     * @var string Our registration endpoint
     */
    const ENDPOINT = 'http://cerberus.fabric.dreamfactory.com/api/drupal';

    /**
     * @param User  $user
     * @param array $payload
     *
     * @return bool
     */
    public static function registerUser($user, $payload = [])
    {
        $installType = 'Standalone Package';
        if (false !== stripos(env('DB_DATABASE', ''), 'bitnami')) {
            $installType = 'Bitnami Package';
        }
        $payload = array_merge(
            [
                //	Requirements
                'user_id'             => 5, // required for access, for now
                'email'               => $user->email,
                'name'                => $user->name,
                'first_name'          => $user->first_name,
                'last_name'           => $user->last_name,
                'installation_source' => $installType
            ],
            ArrayUtils::clean($payload)
        );

        $payload = json_encode($payload);
        $options = [CURLOPT_HTTPHEADER => ['Content-Type: application/json']];

        if (false !== ($_response = Curl::post(static::ENDPOINT . '/contact/', $payload, $options))) {
            if ($_response && $_response->success) {
                return true;
            }
        }

        return false;
    }

}