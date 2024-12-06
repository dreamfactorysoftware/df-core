<?php

namespace App\Utility;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;
use DreamFactory\Core\Utility\Environment\Platform;

class UpdatesSender
{
    /**
     * Sends fresh instance data to updates server
     * 
     * @param array $userData User data from first admin creation
     * @return void
     */
    public static function sendFreshInstanceData($userData)
    {
        try {
            if (!Session::isAuthenticated()) {
                return;
            }

            $data = [
                'email' => $userData['email'] ?? '',
                'ip_address' => getHostByName(getHostName()),
                'install_type' => env('DF_INSTALL', 'unknown'),
                'phone_number' => $userData['phone'] ?? '',
                'license_level' => Platform::getLicenseLevel(),
                'license_key' => env('DF_LICENSE_KEY', '')
            ];

            $client = new Client([
                'timeout' => 2,
                'verify' => false
            ]);

            $client->postAsync('https://updates.dreamfactory.com/api/fresh-instances', [
                'json' => $data
            ])->then(
                function ($response) {
                    \Log::debug('Fresh instance data sent successfully');
                },
                function ($exception) {
                    \Log::debug('Failed to send fresh instance data: ' . $exception->getMessage());
                }
            );
        } catch (\Exception $e) {
            // Fail silently
            \Log::debug('Error sending fresh instance data: ' . $e->getMessage());
        }
    }
} 