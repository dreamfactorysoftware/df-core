<?php

namespace DreamFactory\Core\Utility;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use DreamFactory\Core\Utility\Environment;

class UpdatesSender
{
    public static function sendFreshInstanceData($userData, $skipAuthCheck = false)
    {
        try {
            if (!$skipAuthCheck && !Session::isAuthenticated()) {
                return;
            }

            $data = [
                'email' => $userData['email'] ?? '',
                'ip_address' => getHostByName(getHostName()),
                'install_type' => env('DF_INSTALL', 'unknown'),
                'phone_number' => $userData['phone'] ?? '',
                'license_level' => Environment::getLicenseLevel(),
                'license_key' => env('DF_LICENSE_KEY', 'unknown'),
                'version' => Config::get('app.version'),
                'server_os' => strtolower(php_uname('a'))
            ];

            $client = new Client([
                'timeout' => 2,
                'verify' => false
            ]);

            $promise = $client->postAsync('https://updates.dreamfactory.com/api/fresh-instances', [
                'json' => $data
            ]);

            $promise->wait();

        } catch (\Exception $e) {
            // Fail silently
        }
    }
} 