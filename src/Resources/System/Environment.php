<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\AppTypes;
use DreamFactory\Core\Models\App as AppModel;
use DreamFactory\Core\Models\AppGroup as AppGroupModel;
use DreamFactory\Core\Models\Service as ServiceModel;
use DreamFactory\Core\Models\UserAppRole;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\User\Services\User;
use DreamFactory\Core\Utility\Session as SessionUtilities;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Scalar;

class Environment extends BaseRestResource
{
    /**
     * @return array
     */
    protected function handleGET()
    {
        $result = [];

        $result['platform'] = [
            'version_current'   => '2.0.0',
            'version_latest'    => '2.0.0',
            'upgrade_available' => false,
            'is_hosted'         => false,
            'host'              => php_uname('n'),
        ];

        if (SessionUtilities::isSysAdmin()) {
            $result['server'] = [
                'server_os' => strtolower(php_uname('s')),
                'release'   => php_uname('r'),
                'version'   => php_uname('v'),
                'host'      => php_uname('n'),
                'machine'   => php_uname('m'),
            ];
            $result['php'] = static::getPhpInfo();
        }

        $login = static::getLoginApi();
        $apps = static::getApps();

        $result['authentication'] = $login;
        $result['app_group'] = ArrayUtils::get($apps, 0, []);
        $result['no_group_app'] = ArrayUtils::get($apps, 1, []);

        return $result;
    }

    protected static function getApps()
    {
        if (SessionUtilities::isAuthenticated()) {
            $user = SessionUtilities::user();
            $defaultAppId = $user->default_app_id;

            if (SessionUtilities::isSysAdmin()) {
                $appGroups = AppGroupModel::with(
                    [
                        'app_by_app_to_app_group' => function ($q){
                            $q->whereIsActive(1)->whereIn('type', [AppTypes::PATH, AppTypes::URL]);
                        }
                    ]
                )->get();
                $apps = AppModel::whereIsActive(1)->whereIn('type', [AppTypes::PATH, AppTypes::URL])->get();
            } else {
                $userId = $user->id;
                $userAppRoles = UserAppRole::whereUserId($userId)->whereNotNull('role_id')->get(['app_id']);
                $appIds = [];
                foreach ($userAppRoles as $uar) {
                    $appIds[] = $uar->app_id;
                }
                $appGroups = AppGroupModel::with(
                    [
                        'app_by_app_to_app_group' => function ($q) use ($appIds){
                            $q->whereIn('app.id', $appIds)
                                ->OrWhere('role_id', '>', 0)
                                ->whereIsActive(1)
                                ->whereIn('type', [AppTypes::PATH, AppTypes::URL]);
                        }
                    ]
                )->get();
                $apps = AppModel::whereIn('id', $appIds)
                    ->OrWhere('role_id', '>', 0)
                    ->whereIsActive(1)
                    ->whereIn('type', [AppTypes::PATH, AppTypes::URL])
                    ->get();
            }
        } else {
            $appGroups = AppGroupModel::with(
                [
                    'app_by_app_to_app_group' => function ($q){
                        $q->where('role_id', '>', 0)
                            ->whereIsActive(1)
                            ->whereIn('type', [AppTypes::PATH, AppTypes::URL]);
                    }
                ]
            )->get();
            $apps = AppModel::whereIsActive(1)
                ->where('role_id', '>', 0)
                ->whereIn('type', [AppTypes::PATH, AppTypes::URL])
                ->get();
        }

        if (empty($defaultAppId)) {
            $systemConfig = \DB::table('system_config')->first(['default_app_id']);
            $defaultAppId = ($systemConfig) ? $systemConfig->default_app_id : null;
        }

        $inGroups = [];
        $groupedApps = [];
        $noGroupedApps = [];

        foreach ($appGroups as $appGroup) {
            $appArray = $appGroup->getRelation('app_by_app_to_app_group')->toArray();
            if (!empty($appArray)) {
                $appInfo = [];
                foreach ($appArray as $app) {
                    $inGroups[] = $app['id'];
                    $appInfo[] = static::makeAppInfo($app, $defaultAppId);
                }

                $groupedApps[] = [
                    'id'          => $appGroup->id,
                    'name'        => $appGroup->name,
                    'description' => $appGroup->description,
                    'app'         => $appInfo
                ];
            }
        }

        foreach ($apps as $app) {
            if (!in_array($app->id, $inGroups)) {
                $noGroupedApps[] = static::makeAppInfo($app->toArray(), $defaultAppId);
            }
        }

        return [$groupedApps, $noGroupedApps];
    }

    protected static function makeAppInfo(array $app, $defaultAppId)
    {
        return [
            'id'                      => $app['id'],
            'name'                    => $app['name'],
            'description'             => $app['description'],
            'url'                     => $app['url'],
            'path'                    => $app['path'],
            'is_default'              => ($defaultAppId === $app['id']) ? true : false,
            'allow_fullscreen_toggle' => $app['allow_fullscreen_toggle'],
            'requires_fullscreen'     => $app['requires_fullscreen'],
            'toggle_location'         => $app['toggle_location']
        ];
    }

    /**
     * @return array
     */
    protected static function getLoginApi()
    {
        $adminApi = [
            'path'    => 'system/admin/session',
            'verb'    => Verbs::POST,
            'payload' => [
                'email'       => 'string',
                'password'    => 'string',
                'remember_me' => 'bool'
            ]
        ];
        $userApi = [
            'path'    => 'user/session',
            'verb'    => Verbs::POST,
            'payload' => [
                'email'       => 'string',
                'password'    => 'string',
                'remember_me' => 'bool'
            ]
        ];

        if (class_exists(User::class)) {
            $oauth = static::getOAuthServices();
            $ldap = static::getAdLdapServices();

            return [
                'admin'  => $adminApi,
                'user'   => $userApi,
                'oauth'  => $oauth,
                'adldap' => $ldap
            ];
        }

        return ['admin' => $adminApi];
    }

    /**
     * @return array
     */
    protected static function getOAuthServices()
    {
        $oauth = ServiceModel::whereIn(
            'type',
            ['oauth_facebook', 'oauth_twitter', 'oauth_github', 'oauth_google']
        )->whereIsActive(1)->get(['id', 'name', 'type', 'label']);

        $services = [];

        foreach ($oauth as $o) {
            $config = $o->getConfigAttribute();
            $services[] = [
                'path'       => 'user/session?service=' . strtolower($o->name),
                'name'       => $o->name,
                'label'      => $o->label,
                'verb'       => [Verbs::GET, Verbs::POST],
                'type'       => $o->type,
                'icon_class' => $config['icon_class']
            ];
        }

        return $services;
    }

    /**
     * @return array
     */
    protected static function getAdLdapServices()
    {
        $ldap = ServiceModel::whereIn(
            'type',
            ['ldap', 'adldap']
        )->whereIsActive(1)->get(['name', 'type', 'label']);

        $services = [];

        foreach ($ldap as $l) {
            $services[] = [
                'path'    => 'user/session?service=' . strtolower($l->name),
                'name'    => $l->name,
                'label'   => $l->label,
                'verb'    => Verbs::POST,
                'payload' => [
                    'username'    => 'string',
                    'password'    => 'string',
                    'service'     => $l->name,
                    'remember_me' => 'bool'
                ]
            ];
        }

        return $services;
    }

    //Following codes are directly copied over from 1.x and is not functional.

//    protected function handleGET()
//    {
//        $_release = null;
//        $_phpInfo = $this->_getPhpInfo();
//
//        if ( false !== ( $_raw = file( static::LSB_RELEASE ) ) && !empty( $_raw ) )
//        {
//            $_release = array();
//
//            foreach ( $_raw as $_line )
//            {
//                $_fields = explode( '=', $_line );
//                $_release[str_replace( 'distrib_', null, strtolower( $_fields[0] ) )] = trim( $_fields[1], PHP_EOL . '"' );
//            }
//        }
//
//        $_response = array(
//            'php_info' => $_phpInfo,
//            'platform' => Config::getCurrentConfig(),
//            'release'  => $_release,
//            'server'   => array(
//                'server_os' => strtolower( php_uname( 's' ) ),
//                'uname'     => php_uname( 'a' ),
//            ),
//        );
//
//        array_multisort( $_response );
//
//        //	Cache configuration
//        Platform::storeSet( static::CACHE_KEY, $_response, static::CONFIG_CACHE_TTL );
//
//        $this->_response = $this->_response ? array_merge( $this->_response, $_response ) : $_response;
//        unset( $_response );
//
//        return $this->_response;
//    }

    /**
     * Parses the data coming back from phpinfo() call and returns in an array
     *
     * @return array
     */
    protected static function getPhpInfo()
    {
        $_html = null;
        $_info = array();
        $_pattern =
            '#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s';

        \ob_start();
        @\phpinfo();
        $_html = \ob_get_contents();
        \ob_end_clean();

        if (preg_match_all($_pattern, $_html, $_matches, PREG_SET_ORDER)) {
            foreach ($_matches as $_match) {
                $_keys = array_keys($_info);
                $_lastKey = end($_keys);

                if (strlen($_match[1])) {
                    $_info[$_match[1]] = array();
                } elseif (isset($_match[3])) {
                    $_info[$_lastKey][$_match[2]] = isset($_match[4]) ? array($_match[3], $_match[4]) : $_match[3];
                } else {
                    $_info[$_lastKey][] = $_match[2];
                }

                unset($_keys, $_match);
            }
        }

        return static::cleanPhpInfo($_info);
    }

    /**
     * @param array $info
     *
     * @param bool  $recursive
     *
     * @return array
     */
    protected static function cleanPhpInfo($info, $recursive = false)
    {
        static $_excludeKeys = array('directive', 'variable',);

        $_clean = array();

        //  Remove images and move nested args to root
        if (!$recursive && isset($info[0], $info[0][0]) && is_array($info[0])) {
            $info['general'] = array();

            foreach ($info[0] as $_key => $_value) {
                if (is_numeric($_key) || in_array(strtolower($_key), $_excludeKeys)) {
                    continue;
                }

                $info['general'][$_key] = $_value;
                unset($info[0][$_key]);
            }

            unset($info[0]);
        }

        foreach ($info as $_key => $_value) {
            if (in_array(strtolower($_key), $_excludeKeys)) {
                continue;
            }

            $_key = strtolower(str_replace(' ', '_', $_key));

            if (is_array($_value) && 2 == count($_value) && isset($_value[0], $_value[1])) {
                $_v1 = ArrayUtils::get($_value, 0);

                if ($_v1 == '<i>no value</i>') {
                    $_v1 = null;
                }

                if (Scalar::in(strtolower($_v1), 'on', 'off', '0', '1')) {
                    $_v1 = ArrayUtils::getBool($_value, 0);
                }

                $_value = $_v1;
            }

            if (is_array($_value)) {
                $_value = static::cleanPhpInfo($_value, true);
            }

            $_clean[$_key] = $_value;
        }

        return $_clean;
    }
    /*
    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName( '.' );

        return [

            //-------------------------------------------------------------------------
            //	APIs
            //-------------------------------------------------------------------------

            'apis'   => [
                [
                    'path'        => $path,
                    'operations'  => [
                        [
                            'method'     => 'GET',
                            'summary'    => 'getEnvironment() - Retrieve environment information.',
                            'nickname'   => 'getEnvironment',
                            'type'       => 'EnvironmentResponse',
                            'event_name' => $eventPath . '.read',
                            'notes'      => 'The retrieved information describes the container/machine on which the DSP resides.',
                        ],
                    ],
                    'description' => 'Operations for system configuration options.',
                ],
            ],
            //-------------------------------------------------------------------------
            //	Models
            //-------------------------------------------------------------------------

            'models' => [
                'ServerSection'       => [
                    'id'         => 'ServerSection',
                    'properties' => [
                        'server_os' => [
                            'type' => 'string',
                        ],
                        'uname'     => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'ReleaseSection'      => [
                    'id'         => 'ReleaseSection',
                    'properties' => [
                        'id'          => [
                            'type' => 'string',
                        ],
                        'release'     => [
                            'type' => 'string',
                        ],
                        'codename'    => [
                            'type' => 'string',
                        ],
                        'description' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'PlatformSection'     => [
                    'id'         => 'PlatformSection',
                    'properties' => [
                        'is_hosted'           => [
                            'type' => 'boolean',
                        ],
                        'is_private'          => [
                            'type' => 'boolean',
                        ],
                        'dsp_version_current' => [
                            'type' => 'string',
                        ],
                        'dsp_version_latest'  => [
                            'type' => 'string',
                        ],
                        'upgrade_available'   => [
                            'type' => 'boolean',
                        ],
                    ],
                ],
                'PhpInfoSection'      => [
                    'id'         => 'PhpInfoSection',
                    'properties' => [
                        'name' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                'EnvironmentResponse' => [
                    'id'         => 'EnvironmentResponse',
                    'properties' => [
                        'server'   => [
                            'type' => 'ServerSection',
                        ],
                        'release'  => [
                            'type' => 'ReleaseSection',
                        ],
                        'platform' => [
                            'type' => 'PlatformSection',
                        ],
                        'php_info' => [
                            'type'  => 'array',
                            'items' => [
                                '$ref' => 'PhpInfoSection',
                            ],
                        ],
                    ],
                ],
            ]
        ];
    }
    */
}