<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\Curl;

class RegisterContact
{
    /**
     * @var string Our registration endpoint
     */
    const ENDPOINT = 'https://www.dreamfactory.com/in_product_v2/registration.php';

    /**
     * @param User  $user
     * @param array $payload
     *
     * @return bool
     */
    public static function registerUser($user, array $payload = [])
    {
        $source = 'Product Install DreamFactory';
        if (config('df.managed')) {
            $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
            if (false === strpos($serverName, '.enterprise.dreamfactory.com')) {
                return true; // bail, not tracking
            }
            $source = 'Website Free Hosted';
        }
        $partner = env('DF_INSTALL', '');
        if (empty($partner) && (false !== stripos(env('DB_DATABASE', ''), 'bitnami'))) {
            $partner = 'Bitnami';
        }
        $payload = array_merge(
            [
                'email'       => $user->email,
                'name'        => $user->name,
                'firstname'   => $user->first_name,
                'lastname'    => $user->last_name,
                'phone'       => $user->phone,
                'lead_event'  => $source,
                'lead_source' => $source,
                'partner'     => $partner,
                'product'     => 'DreamFactory',
                'version'     => config('df.version', 'unknown'),
                'host_os'     => PHP_OS,
            ],
            $payload
        );

        $payload = json_encode($payload);
        $options = [CURLOPT_HTTPHEADER => ['Content-Type: application/json']];

        if (false !== ($_response = Curl::post(static::ENDPOINT, $payload, $options))) {
            return true;
        }

        return false;
    }

}