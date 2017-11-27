<?php

namespace DreamFactory\Core\Http\Controllers;

use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Environment;
use DreamFactory\Core\Utility\ResponseFactory;

class StatusController extends Controller
{
    public function index()
    {
        \Log::info('[REQUEST] Instance status');

        $uri = Environment::getURI();

        $dist = env('DF_INSTALL', '');
        if (empty($dist) && (false !== stripos(env('DB_DATABASE', ''), 'bitnami'))) {
            $dist = 'Bitnami';
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $appCount = App::count();
        $adminCount = User::whereIsSysAdmin(1)->count();
        $userCount = User::whereIsSysAdmin(0)->count();
        $lastAdminLogin = User::whereIsSysAdmin(1)->orderBy('last_login_date', 'desc')->value('last_login_date');
        /** @noinspection PhpUndefinedMethodInspection */
        $serviceCount = Service::count();
        /** @noinspection PhpUndefinedMethodInspection */
        $roleCount = Role::count();

        $status = [
            'uri'              => $uri,
            'managed'          => env('DF_MANAGED', false),
            'dist'             => $dist,
            'demo'             => Environment::isDemoApplication(),
            'version'          => \Config::get('app.version'),
            'license'          => Environment::getLicenseLevel(),
            'host_os'          => PHP_OS,
            'last_admin_login' => $lastAdminLogin,
            'resources'        => [
                'app'     => $appCount,
                'admin'   => $adminCount,
                'user'    => $userCount,
                'service' => $serviceCount,
                'role'    => $roleCount
            ]
        ];

        return ResponseFactory::sendResponse(ResponseFactory::create($status));
    }
}