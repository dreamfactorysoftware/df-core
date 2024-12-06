<?php

namespace DreamFactory\Core\Utility;

use GuzzleHttp\Client;
use DreamFactory\Core\System\Resources\System;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class UpdatesSender
{
    /**
     * Sends fresh instance data to updates server
     * 
     * @param array $userData User data from first admin creation
     * @param bool $skipAuthCheck Skip the session authentication check for first admin creation
     * @return void
     */
    public static function sendFreshInstanceData($userData, $skipAuthCheck = false)
    {
        try {
            Log::debug('Attempting to send fresh instance data');
            Log::debug('User data received:', $userData);
            
            // Only check authentication if not creating first admin
            if (!$skipAuthCheck && !Session::isAuthenticated()) {
                Log::debug('Auth check failed - skipping fresh instance data send');
                return;
            }

            $data = [
                'email' => $userData['email'] ?? '',
                'ip_address' => getHostByName(getHostName()),
                'install_type' => env('DF_INSTALL', 'unknown'),
                'phone_number' => $userData['phone'] ?? '',
                'license_level' => 'community',
                'license_key' => env('DF_LICENSE_KEY', 'unknown')
            ];

            Log::debug('Preparing to send data to updates server:', $data);

            $client = new Client([
                'timeout' => 2,
                'verify' => false
            ]);

            $promise = $client->postAsync('https://updates.dreamfactory.com/api/fresh-instances', [
                'json' => $data
            ]);

            Log::debug('Request initiated');

            $promise->then(
                function ($response) {
                    Log::debug('Fresh instance data sent successfully. Response: ' . $response->getBody());
                },
                function ($exception) {
                    Log::debug('Failed to send fresh instance data: ' . $exception->getMessage());
                }
            );

            // Force the promise to complete
            $promise->wait();

        } catch (\Exception $e) {
            // Fail silently but log the error
            Log::debug('Error in sendFreshInstanceData: ' . $e->getMessage());
            Log::debug($e->getTraceAsString());
        }
    }
} 