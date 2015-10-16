<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Managed\Support\Managed;

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
        $leadsource = 'product-install';
        if (!config('df.standalone')){
            if (false === strpos(gethostname(), '.enterprise.dreamfactory.com')){
                return true; // bail, not tracking
            }
            $leadsource = 'website-freehosted';
        }
        $partner = env('DF_INSTALL', '');
        if (empty($partner) && (false !== stripos(env('DB_DATABASE', ''), 'bitnami'))) {
            $partner = 'bitnami';
        }
        $payload = array_merge(
            [
                //	Requirements
                'user_id'             => 5, // required for access, for now
                'email'               => $user->email,
                'name'                => $user->name,
                'first_name'          => $user->first_name,
                'last_name'           => $user->last_name,
                'leadsource' => $leadsource,
                'partner' => $partner,
                'product' => 'dreamfactory',
                'version' => config('df.version', 'unknown'),
                'hostos' => PHP_OS,
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