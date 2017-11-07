<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\AppTypes;
use DreamFactory\Core\Enums\LicenseLevel;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Models\App as AppModel;
use DreamFactory\Core\Models\Config as SystemConfig;
use DreamFactory\Core\Models\Service as ServiceModel;
use DreamFactory\Core\Models\UserAppRole;
use DreamFactory\Core\Utility\Curl;
use DreamFactory\Core\Utility\Session as SessionUtilities;
use Illuminate\Validation\ValidationException;
use ServiceManager;
use Validator;

class Environment extends BaseSystemResource
{
    /**
     * @return array
     */
    protected function handleGET()
    {
        $result = [];

        // Required when no authentication is provided
        $result['authentication'] = static::getLoginApi(); // auth options
        $result['apps'] = (array)static::getApps(); // app options

        // authenticated in some way or default app role, show the following
        if (SessionUtilities::isAuthenticated() || SessionUtilities::getRoleId()) {
            $result['platform'] = [
                'version'                => \Config::get('app.version'),
                'bitnami_demo'           => static::isDemoApplication(),
                'is_hosted'              => to_bool(env('DF_MANAGED', false)),
                'license'                => static::getLicenseLevel(),
                'secured_package_export' => static::isZipInstalled(),
            ];

            // including information that helps users use the API or debug
            $result['server'] = [
                'server_os' => strtolower(php_uname('s')),
                'release'   => php_uname('r'),
                'version'   => php_uname('v'),
                'host'      => php_uname('n'),
                'machine'   => php_uname('m'),
                'ip'        => static::getExternalIP()
            ];

            $result['client'] = [
                "user_agent" => \Request::header('User-Agent'),
                "ip_address" => \Request::getClientIp(),
                "locale"     => \Request::getLocale()
            ];

            /*
             * Most API calls return a resource array or a single resource,
             * If an array, shall we wrap it?, With what shall we wrap it?
             */
            $result['config'] = [
                'always_wrap_resources' => \Config::get('df.always_wrap_resources'),
                'resources_wrapper'     => \Config::get('df.resources_wrapper'),
                'db'                    => [
                    /** The default number of records to return at once for database queries */
                    'max_records_returned' => \Config::get('database.max_records_returned'),
                    'time_format'          => \Config::get('df.db.time_format'),
                    'date_format'          => \Config::get('df.db.date_format'),
                    'datetime_format'      => \Config::get('df.db.datetime_format'),
                    'timestamp_format'     => \Config::get('df.db.timestamp_format'),
                ],
            ];

            if (SessionUtilities::isSysAdmin()) {
                // administrator-only information
                $dbDriver = \Config::get('database.default');
                $result['platform']['db_driver'] = $dbDriver;
                if ($dbDriver === 'sqlite') {
                    $result['platform']['sqlite_storage'] = \Config::get('df.db.sqlite_storage');
                }
                $result['platform']['install_path'] = base_path() . DIRECTORY_SEPARATOR;
                $result['platform']['log_path'] = env('DF_MANAGED_LOG_PATH',
                        storage_path('logs')) . DIRECTORY_SEPARATOR;
                $result['platform']['log_mode'] = \Config::get('app.log');
                $result['platform']['log_level'] = \Config::get('app.log_level');
                $result['platform']['cache_driver'] = \Config::get('cache.default');

                if ($result['platform']['cache_driver'] === 'file') {
                    $result['platform']['cache_path'] = \Config::get('cache.stores.file.path') . DIRECTORY_SEPARATOR;
                }

                $packages = static::getInstalledPackagesInfo();
                $result['platform']['packages'] = $packages;

                $result['php'] = static::getPhpInfo();
            }
        }

        return $result;
    }

    /**
     * Checks to see if zip command is installed or not.
     *
     * @return bool
     */
    public static function isZipInstalled()
    {
        exec('zip -h', $output, $ret);

        return ($ret === 0) ? true : false;
    }

    /**
     * Returns instance's external IP address.
     *
     * @return mixed
     */
    public static function getExternalIP()
    {
        $ip = \Cache::rememberForever('external-ip-address', function () {
            $response = Curl::get('http://ipinfo.io/ip');
            $ip = trim($response, "\t\r\n");
            try {
                $validator = Validator::make(['ip' => $ip], ['ip' => 'ip']);
                $validator->validate();
            } catch (ValidationException $e) {
                $ip = null;
            }

            return $ip;
        });

        return $ip;
    }

    /**
     * @return string
     */
    public static function getLicenseLevel()
    {
        $silver = false;
        foreach (ServiceManager::getServiceTypes() as $typeInfo) {
            switch ($typeInfo->subscriptionRequired()) {
                case LicenseLevel::GOLD:
                    return LicenseLevel::GOLD; // highest level, bail here
                case LicenseLevel::SILVER:
                    $silver = true; // finish loop to make sure there is no gold
                    break;
            }
        }

        if ($silver) {
            return LicenseLevel::SILVER;
        }

        return LicenseLevel::OPEN_SOURCE;
    }

    public static function getInstalledPackagesInfo()
    {
        $lockFile = base_path() . DIRECTORY_SEPARATOR . 'composer.lock';
        $result = [];

        try {
            if (file_exists($lockFile)) {
                $json = file_get_contents($lockFile);
                $array = json_decode($json, true);
                $packages = array_get($array, 'packages', []);

                foreach ($packages as $package) {
                    $name = array_get($package, 'name');
                    $result[] = [
                        'name'    => $name,
                        'version' => array_get($package, 'version')
                    ];
                }
            } else {
                \Log::warning(
                    'Failed to get installed packages information. composer.lock file not found at ' .
                    $lockFile
                );
                $result = ['error' => 'composer.lock file not found'];
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get installed packages information. ' . $e->getMessage());
            $result = ['error' => 'Failed to get installed packages information. See log for details.'];
        }

        return $result;
    }

    /**
     * Determines whether the instance is a one hour bitnami demo.
     *
     * @return bool
     */
    public static function isDemoApplication()
    {
        return file_exists($_SERVER["DOCUMENT_ROOT"] . "/../../.bitnamimeta/demo_machine");
    }

    protected static function getApps()
    {
        if (SessionUtilities::isAuthenticated()) {
            $user = SessionUtilities::user();
            $defaultAppId = $user->default_app_id;

            if (SessionUtilities::isSysAdmin()) {
                $apps = AppModel::whereIsActive(1)->whereNotIn('type', [AppTypes::NONE])->get();
            } else {
                $userId = $user->id;
                $userAppRoles = UserAppRole::whereUserId($userId)->whereNotNull('role_id')->get(['app_id']);
                $appIds = [];
                foreach ($userAppRoles as $uar) {
                    $appIds[] = $uar->app_id;
                }
                $appIdsString = implode(',', $appIds);
                $appIdsString = (empty($appIdsString)) ? '-1' : $appIdsString;
                $typeString = implode(',', [AppTypes::NONE]);
                $typeString = (empty($typeString)) ? '-1' : $typeString;

                $apps =
                    AppModel::whereRaw("(app.id IN ($appIdsString) OR role_id > 0) AND is_active = 1 AND type NOT IN ($typeString)")
                        ->get();
            }
        } else {
            $apps = AppModel::whereIsActive(1)
                ->where('role_id', '>', 0)
                ->whereNotIn('type', [AppTypes::NONE])
                ->get();
        }

        if (empty($defaultAppId)) {
            $systemConfig = SystemConfig::first(['default_app_id']);
            $defaultAppId = (!empty($systemConfig)) ? $systemConfig->default_app_id : null;
        }

        $out = [];
        /** @type AppModel $app */
        foreach ($apps as $app) {
            $out[] = static::makeAppInfo($app->toArray(), $defaultAppId);
        }

        return $out;
    }

    protected static function makeAppInfo(array $app, $defaultAppId)
    {
        return [
            'id'                      => $app['id'],
            'name'                    => $app['name'],
            'description'             => $app['description'],
            'url'                     => $app['launch_url'],
            'is_default'              => ($defaultAppId === $app['id']) ? true : false,
            'allow_fullscreen_toggle' => $app['allow_fullscreen_toggle'],
            'requires_fullscreen'     => $app['requires_fullscreen'],
            'toggle_location'         => $app['toggle_location'],
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
                'remember_me' => 'bool',
            ],
        ];
        $userApi = [
            'path'    => 'user/session',
            'verb'    => Verbs::POST,
            'payload' => [
                'email'       => 'string',
                'password'    => 'string',
                'remember_me' => 'bool',
            ],
        ];

        if (class_exists('\DreamFactory\Core\User\Services\User')) {
            $oauth = static::getOAuthServices();
            $ldap = static::getAdLdapServices();
            $saml = static::getSamlServices();

            /** @var \DreamFactory\Core\User\Services\User $userService */
            $userService = ServiceManager::getService('user');

            return [
                'admin'                     => $adminApi,
                'user'                      => $userApi,
                'oauth'                     => $oauth,
                'adldap'                    => $ldap,
                'saml'                      => $saml,
                'allow_open_registration'   => $userService->allowOpenRegistration,
                'open_reg_email_service_id' => $userService->openRegEmailServiceId,
                'allow_forever_sessions'    => config('df.allow_forever_sessions', false),
                'login_attribute'           => strtolower(config('df.login_attribute', 'email'))
            ];
        }

        return [
            'admin'                     => $adminApi,
            'allow_open_registration'   => false,
            'open_reg_email_service_id' => null,
        ];
    }

    /**
     * @return array
     */
    protected static function getOAuthServices()
    {
        $types = [];
        foreach (ServiceManager::getServiceTypes('oauth') as $type) {
            $types[] = $type->getName();
        }

        /** @var ServiceModel[] $oauth */
        /** @noinspection PhpUndefinedMethodInspection */
        $oauth = ServiceModel::whereIn('type', $types)->whereIsActive(1)->get(['id', 'name', 'type', 'label']);

        $services = [];
        foreach ($oauth as $o) {
            $config = ($o->getConfigAttribute()) ?: [];
            $services[] = [
                'path'       => 'user/session?service=' . strtolower($o->name),
                'name'       => $o->name,
                'label'      => $o->label,
                'verb'       => [Verbs::GET, Verbs::POST],
                'type'       => $o->type,
                'icon_class' => array_get($config, 'icon_class'),
            ];
        }

        return $services;
    }

    protected static function getSamlServices()
    {
        $samls = ServiceModel::whereType('saml')->whereIsActive(1)->get(['id', 'name', 'type', 'label']);

        $services = [];
        foreach ($samls as $saml) {
            $config = ($saml->getConfigAttribute()) ?: [];
            $services[] = [
                'path'       => $saml->name . '/sso',
                'name'       => $saml->name,
                'label'      => $saml->label,
                'verb'       => Verbs::GET,
                'type'       => 'saml',
                'icon_class' => array_get($config, 'icon_class'),
            ];
        }

        return $services;
    }

    /**
     * @return array
     */
    protected static function getAdLdapServices()
    {
        /** @noinspection PhpUndefinedMethodInspection */
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
                    'remember_me' => 'bool',
                ],
            ];
        }

        return $services;
    }

    /**
     * Returns instance's URI
     *
     * @return string
     */
    public static function getURI()
    {
        $s = $_SERVER;
        $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
        $sp = strtolower(array_get($s, 'SERVER_PROTOCOL', 'http://'));
        $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
        $port = array_get($s, 'SERVER_PORT', '80');
        $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
        $host = (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : array_get($s, 'SERVER_NAME', 'localhost'));
        $host = (strpos($host, ':') !== false) ? $host : $host . $port;

        return $protocol . '://' . $host;
    }

    /**
     * Parses the data coming back from phpinfo() call and returns in an array
     *
     * @return array
     */
    protected static function getPhpInfo()
    {
        $html = null;
        $info = [];
        $pattern =
            '#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s';

        \ob_start();
        @\phpinfo();
        $html = \ob_get_contents();
        \ob_end_clean();

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $keys = array_keys($info);
                $lastKey = end($keys);

                if (strlen($match[1])) {
                    $info[$match[1]] = [];
                } elseif (isset($match[3])) {
                    $info[$lastKey][$match[2]] = isset($match[4]) ? [$match[3], $match[4]] : $match[3];
                } else {
                    $info[$lastKey][] = $match[2];
                }

                unset($keys, $match);
            }
        }

        return static::cleanPhpInfo($info);
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
        static $excludeKeys = ['directive', 'variable',];

        $clean = [];

        //  Remove images and move nested args to root
        if (!$recursive && isset($info[0], $info[0][0]) && is_array($info[0])) {
            $info['general'] = [];

            foreach ($info[0] as $key => $value) {
                if (is_numeric($key) || in_array(strtolower($key), $excludeKeys)) {
                    continue;
                }

                $info['general'][$key] = $value;
                unset($info[0][$key]);
            }

            unset($info[0]);
        }

        foreach ($info as $key => $value) {
            if (in_array(strtolower($key), $excludeKeys)) {
                continue;
            }

            $key = strtolower(str_replace(' ', '_', $key));

            if (is_array($value) && 2 == count($value) && isset($value[0], $value[1])) {
                $v1 = array_get($value, 0);

                if ($v1 == '<i>no value</i>') {
                    $v1 = null;
                }

                if (in_array(strtolower($v1), ['on', 'off', '0', '1'])) {
                    $v1 = array_get_bool($value, 0);
                }

                $value = $v1;
            }

            if (is_array($value)) {
                $value = static::cleanPhpInfo($value, true);
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $resourceName = strtolower($this->name);

        return [
            '/' . $resourceName => [
                'get' => [
                    'summary'     => 'Retrieve system environment.',
                    'description' =>
                        'Minimum environment information given without a valid user session.' .
                        ' More information given based on user privileges.',
                    'operationId' => 'get' . $capitalized . 'Environment',
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/EnvironmentResponse']
                    ],
                ],
            ],
        ];
    }

    protected function getApiDocRequests()
    {
        return [];
    }

    protected function getApiDocResponses()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');

        return [
            $class . 'Response' => [
                'description' => 'Response',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Response']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Response']
                    ],
                ],
            ],
        ];
    }

    protected function getApiDocSchemas()
    {
        $models = [
            'EnvironmentResponse' => [
                'type'       => 'object',
                'properties' => [
                    'platform'       => [
                        'type'        => 'object',
                        'description' => 'System platform properties.',
                        'properties'  => [
                            'version'   => ['type' => 'string'],
                            'is_hosted' => ['type' => 'boolean'],
                            'host'      => ['type' => 'string'],
                            'license'   => ['type' => 'string'],
                        ],
                    ],
                    'authentication' => [
                        'type'        => 'object',
                        'description' => 'Authentication options for this server.',
                        'properties'  => [
                            'admin'                     => [
                                'type'        => 'object',
                                'description' => 'Admin Authentication.',
                                'properties'  => [
                                    'path'    => ['type' => 'string'],
                                    'verb'    => ['type' => 'boolean'],
                                    'payload' => ['type' => 'string'],
                                ],
                            ],
                            'user'                      => [
                                'type'        => 'object',
                                'description' => 'Admin Authentication.',
                                'properties'  => [
                                    'path'    => ['type' => 'string'],
                                    'verb'    => ['type' => 'boolean'],
                                    'payload' => ['type' => 'string'],
                                ],
                            ],
                            'oauth'                     => [
                                'type'        => 'object',
                                'description' => 'System platform properties.',
                                'properties'  => [
                                    'version'   => ['type' => 'string'],
                                    'is_hosted' => ['type' => 'boolean'],
                                    'host'      => ['type' => 'string'],
                                    'license'   => ['type' => 'string'],
                                ],
                            ],
                            'adldap'                    => [
                                'type'        => 'object',
                                'description' => 'System platform properties.',
                                'properties'  => [
                                    'version'   => ['type' => 'string'],
                                    'is_hosted' => ['type' => 'boolean'],
                                    'host'      => ['type' => 'string'],
                                    'license'   => ['type' => 'string'],
                                ],
                            ],
                            'saml'                      => [
                                'type'        => 'object',
                                'description' => 'System platform properties.',
                                'properties'  => [
                                    'version'   => ['type' => 'string'],
                                    'is_hosted' => ['type' => 'boolean'],
                                    'host'      => ['type' => 'string'],
                                    'license'   => ['type' => 'string'],
                                ],
                            ],
                            'allow_open_registration'   => ['type' => 'boolean'],
                            'open_reg_email_service_id' => ['type' => 'integer', 'format' => 'int32'],
                            'allow_forever_sessions'    => ['type' => 'boolean'],
                            'login_attribute'           => ['type' => 'string'],
                        ],
                    ],
                    'apps'           => [
                        'type'        => 'array',
                        'description' => 'Array of apps.',
                        'items'       => [
                            '$ref' => '#/components/schemas/AppsResponse',
                        ],
                    ],
                    'config'         => [
                        'type'        => 'object',
                        'description' => 'System config properties.',
                        'properties'  => [
                            'resources_wrapper'     => ['type' => 'string'],
                            'always_wrap_resources' => ['type' => 'boolean'],
                            'db'                    => [
                                'type'        => 'object',
                                'description' => 'Database services options.',
                                'properties'  => [
                                    'max_records_returned' => ['type' => 'string'],
                                    'time_format'          => ['type' => 'boolean'],
                                    'date_format'          => ['type' => 'string'],
                                    'timedate_format'      => ['type' => 'string'],
                                    'timestamp_format'     => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'client'         => [
                        'type'        => 'object',
                        'description' => 'Calling client properties.',
                        'properties'  => [
                            'user_agent' => ['type' => 'string'],
                            'ip_address' => ['type' => 'string'],
                            'locale'     => ['type' => 'string'],
                        ],
                    ],
                    'server'         => [
                        'type'        => 'object',
                        'description' => 'System server properties.',
                        'properties'  => [
                            'server_os' => ['type' => 'string'],
                            'release'   => ['type' => 'string'],
                            'version'   => ['type' => 'string'],
                            'machine'   => ['type' => 'string'],
                            'host'      => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        return $models;
    }
}
