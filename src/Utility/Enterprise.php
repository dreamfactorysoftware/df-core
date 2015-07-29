<?php namespace DreamFactory\Core\Utility;

use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\FileSystem;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Library\Utility\Enums\EnterpriseDefaults;
use Symfony\Component\HttpFoundation\Request;
use DreamFactory\Core\Exceptions\ForbiddenException;

/**
 * Methods for interfacing with DreamFactory Enterprise (DFE)
 *
 * This class discovers if this instance is a DFE cluster participant. When the DFE console provisions an instance, it places a configuration file
 * into the root directory of the installation. This file contains the necessary information with which to operate and/or communicate with the its
 * cluster console.
 */
final class Enterprise
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string
     */
    const DEFAULT_DOMAIN = '.pasture.farm.com';
    /**
     * @var string
     */
    const MAINTENANCE_MARKER = '/var/www/.maintenance';
    /**
     * @type string
     */
    const PRIVATE_LOG_PATH_NAME = 'logs';

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type bool Enable/disable debug logging
     */
    private static $debug = true;
    /**
     * @type string
     */
    private static $debugLogFile = '/tmp/debug.log';
    /**
     * @type array
     */
    private static $_config = false;
    /**
     * @type string
     */
    private static $_cacheKey = null;
    /**
     * @type string Our API access token
     */
    private static $_token = null;
    /**
     * @type bool
     */
    protected static $_dfeInstance = false;
    /**
     * @type array The storage paths
     */
    protected static $_paths = array();
    /**
     * @type string The instance name
     */
    protected static $_instanceName = null;
    /**
     * @type bool Set to TRUE to allow updating of cluster environment file.
     */
    protected static $_writableConfig = false;
    /**
     * @type string The root storage directory
     */
    protected static $_storageRoot;
    /**
     * @type array
     */
    protected static $_environment;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Initialization for hosted DSPs
     *
     * @return array
     * @throws \RuntimeException
     * @throws \CHttpException
     */
    public static function initialize()
    {
        static::_makeCacheKey();

        if (config('app.debug') || !static::_reloadCache()) {
            //  Discover where I am
            if (!static::_getClusterConfig()) {
                static::debug('Not a DFE hosted instance. Resistance is NOT futile.');

                return false;
            }
        }

        //  It's all good!
        static::$_dfeInstance = true;
        static::$_instanceName = IfSet::get(static::$_config, 'instance-name');

        //  Generate a signature for signing payloads...
        static::$_token = static::_generateSignature();

        if (!static::_interrogateCluster()) {
            static::debug('Cluster interrogation failed. Suggest water-boarding.');

            throw new \RuntimeException('Unknown managed instance detected. No access allowed.');
        }

        static::debug('dfe instance bootstrap complete.');

        return true;
    }

    /**
     * @return array|bool
     */
    protected static function _interrogateCluster()
    {
        //  Get my config from console
        $_status = static::_api('status', ['id' => static::getInstanceName()]);

        if ($_status instanceof \stdClass) {
            if (!isset( $_status->response, $_status->response->metadata )) {
                static::debug('Invalid or missing response received from DFE console.');

                return false;
            }

            if (!$_status->success) {
                static::debug('Instance not found or unavailable.');

                return false;
            }

            if ($_status->response->archived || $_status->response->deleted) {
                static::debug('Instance has been archived or deleted.');

                return false;
            }
        }

        static::debug('status response: ' . print_r($_status, true));

        //  Storage root is the top-most directory under which all instance storage lives
        static::$_storageRoot =
        static::$_config['storage-root'] = rtrim(static::$_config['storage-root'], ' ' . DIRECTORY_SEPARATOR);

        //  The storage map defines where exactly under $storageRoot the instance's storage resides
        $_map = (array)$_status->response->metadata->{'storage-map'};
        $_paths = (array)$_status->response->metadata->paths;
        $_paths['storage-root'] = static::$_storageRoot;
        $_paths['log-path'] = $_paths['private-path'] . DIRECTORY_SEPARATOR . static::PRIVATE_LOG_PATH_NAME;

        static::debug('response: ' . print_r($_status, true));

        //  prepend real base directory and store in paths
        foreach ($_paths as $_key => $_path) {
            if ('storage-root' !== $_key) {
                static::$_paths[$_key] = static::$_storageRoot . DIRECTORY_SEPARATOR . $_path;
                !is_dir(static::$_paths[$_key]) && mkdir(static::$_paths[$_key], 2755, true);
            }
        }

        static::$_config['paths'] = (array)$_paths;
        static::$_config['storage-map'] = (array)$_map;
        static::$_config['env'] = (array)$_status->response->metadata->env;
        static::$_config['audit'] = (array)$_status->response->metadata->audit;
        isset( $_status->response->{'home-links'} ) && ( static::$_config['home-links'] =
            (array)$_status->response->{'home-links'} );

        //  Get the database config
        if (!empty( $_status->response->metadata->db )) {
            foreach ((array)$_status->response->metadata->db as $_name => $_db) {
                static::$_config['db'] = (array)$_db;
                static::debug('db config found "' . $_name . '": ' . print_r(static::$_config['db'], true));
                break;
            }
        }

        if (empty($_status->response->metadata->limits) === false) {
            static::$_config['limits'] = (array)$_status->response->metadata->limits;
        }

        static::_refreshCache();

        return true;
    }

    /**
     * @return array
     */
    protected static function _validateClusterEnvironment()
    {
        try {
            //  Start out false
            static::$_dfeInstance = false;

            //	If this isn't an enterprise instance, bail
            $_host = static::_getHostName();

            //  And API url
            if (!isset( static::$_config['console-api-url'], static::$_config['console-api-key'] )) {
                static::debug('Invalid configuration: No "console-api-url" or "console-api-key" in cluster manifest.');

                return false;
            }

            //  Make it ready for action...
            static::$_config['console-api-url'] = rtrim(static::$_config['console-api-url'], '/') . '/';

            //  And default domain
            $_defaultDomain = IfSet::get(static::$_config, 'default-domain');

            if (empty( $_defaultDomain ) || false === strpos($_host, $_defaultDomain)) {
                static::debug('Invalid "default-domain" for host "' . $_host . '"');

                return false;
            }

            $_storageRoot = IfSet::get(static::$_config, 'storage-root');

            if (empty( $_storageRoot )) {
                static::debug('No "storage-root" found.');

                return false;
            }

            static::$_config['storage-root'] = rtrim($_storageRoot, ' ' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            static::$_config['default-domain'] = $_defaultDomain = '.' . ltrim($_defaultDomain, '. ');
            static::$_instanceName = str_replace($_defaultDomain, null, $_host);

            //  It's all good!
            return true;
        } catch ( \InvalidArgumentException $_ex ) {
            //  The file is bogus or not there
            return false;
        }
    }

    /**
     * @param string $instanceName
     * @param string $privatePath
     *
     * @return mixed|string
     * @throws \CHttpException
     */
    protected static function _getMetadata($instanceName, $privatePath)
    {
        static $_metadata = null;

        if (!$_metadata) {
            $_mdFile = $privatePath . DIRECTORY_SEPARATOR . $instanceName . '.json';

            if (!file_exists($_mdFile)) {
                static::debug('No instance metadata file found: ' . $_mdFile);

                return false;
            }

            $_metadata = JsonFile::decodeFile($_mdFile);
        }

        return $_metadata;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @return bool|\stdClass|array
     */
    protected static function _api($uri, $payload = array(), $curlOptions = array(), $method = Request::METHOD_POST)
    {
        try {
            //  Allow full URIs or manufacture one...
            if ('http' != substr($uri, 0, 4)) {
                $uri = static::$_config['console-api-url'] . ltrim($uri, '/ ');
            }

            if (false === ( $_result = Curl::request($method, $uri, static::_signPayload($payload), $curlOptions) )) {
                throw new \RuntimeException('Failed to contact API server.');
            }

            if (!( $_result instanceof \stdClass )) {
                if (is_string($_result) && ( false === json_decode($_result) || JSON_ERROR_NONE !== json_last_error() )
                ) {
                    throw new \RuntimeException('Invalid response received from DFE console.');
                }
            }

            return $_result;
        } catch ( \Exception $_ex ) {
            static::debug('api error: ' . $_ex->getMessage());

            return false;
        }
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @param bool   $emptyStringIsNull
     *
     * @return array|mixed
     */
    private static function _getClusterConfig($key = null, $default = null, $emptyStringIsNull = true)
    {
        if (false === static::$_config) {
            $_configFile = static::_locateClusterEnvironmentFile(EnterpriseDefaults::CLUSTER_MANIFEST_FILE);

            if (!$_configFile || !file_exists($_configFile)) {
                return false;
            }

            static::debug('cluster config found: ' . $_configFile);

            try {
                static::$_config = JsonFile::decodeFile($_configFile);

                static::debug('cluster config read: ' . print_r(static::$_config, true));

                if (!static::_validateClusterEnvironment()) {
                    return false;
                }

                //  Re-write the cluster config
                //static::isWritableConfig() && JsonFile::encodeFile( $_configFile, static::$_config );

                //  Stick instance name inside for when pulled from cache...
                if (!empty( static::$_instanceName )) {
                    static::$_config['instance-name'] = static::$_instanceName;
                } else if (isset( static::$_config['instance-name'] )) {
                    static::$_instanceName = static::$_config['instance-name'];
                }
            } catch ( \Exception $_ex ) {
                static::debug('Cluster configuration file is not in a recognizable format.');
                static::$_config = false;

                throw new \RuntimeException('This instance is not configured properly for your system environment.');
            }
        }

        return null === $key ? static::$_config : IfSet::get(static::$_config, $key, $default, $emptyStringIsNull);
    }

    /**
     * @param array $payload
     *
     * @return array
     */
    private static function _signPayload(array $payload)
    {
        return array_merge(
            array(
                'client-id'    => static::$_config['client-id'],
                'access-token' => static::$_token,
            ),
            $payload ?: array()
        );
    }

    /**
     * @return string
     */
    private static function _generateSignature()
    {
        return
            hash_hmac(
                static::$_config['signature-method'],
                static::$_config['client-id'],
                static::$_config['client-secret']
            );
    }

    /**
     * @return boolean
     */
    public static function isManagedInstance()
    {
        return static::$_dfeInstance;
    }

    /**
     * @return string
     */
    public static function getInstanceName()
    {
        return static::$_instanceName;
    }

    /**
     * @return boolean
     */
    public static function isWritableConfig()
    {
        return static::$_writableConfig;
    }

    /**
     * @return string
     */
    public static function getStoragePath()
    {
        return static::$_paths['storage-path'];
    }

    /**
     * @return string
     */
    public static function getPrivatePath()
    {
        return static::$_paths['private-path'];
    }

    /**
     * @param string|null $logfile
     *
     * @return string Absolute /path/to/logs
     */
    public static function getLogPath($logfile = null)
    {
        FileSystem::ensurePath($_path = static::getPrivatePath() . DIRECTORY_SEPARATOR . 'logs');

        return $_path;
    }

    /**
     * @param string|null $name
     *
     * @return string The absolute /path/to/log/file
     */
    public static function getLogFile($name = null)
    {
        return static::getLogPath() . DIRECTORY_SEPARATOR . ( $name ?: static::$_instanceName . '.log' );
    }

    /**
     * @return string
     */
    public static function getOwnerPrivatePath()
    {
        return static::$_paths['owner-private-path'];
    }

    /**
     * Retrieve a config value or the entire array
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return array|mixed
     */
    public static function getConfig($key = null, $default = null)
    {
        if (null === $key) {
            return static::$_config;
        }

        return IfSet::get(static::$_config, $key, $default);
    }

    /**
     * Refreshes the cache with fresh values
     */
    protected static function _refreshCache()
    {

        config([static::$_cacheKey => ['paths' => static::$_paths, 'config' => static::$_config]]);
    }

    /**
     * Reload the cache
     */
    protected static function _reloadCache()
    {
        $_cache = config(static::$_cacheKey);

        if (!empty( $_cache ) && isset( $_cache['paths'], $_cache['config'] )) {
            static::$_paths = $_cache['paths'];

            return static::$_config = $_cache['config'];
        }

        return false;
    }

    /**
     * Locate the configuration file for DFE, if any
     *
     * @param string $file
     *
     * @return bool|string
     */
    protected static function _locateClusterEnvironmentFile($file)
    {
        $_path = isset( $_SERVER, $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : getcwd();

        while (true) {
            if (file_exists($_path . DIRECTORY_SEPARATOR . $file)) {
                return $_path . DIRECTORY_SEPARATOR . $file;
            }

            $_parentPath = dirname($_path);

            if ($_parentPath == $_path || empty( $_parentPath ) || $_parentPath == DIRECTORY_SEPARATOR) {
                return false;
            }

            $_path = $_parentPath;
        }

        return false;
    }

    /**
     * Gets my host name
     *
     * @return string
     */
    protected static function _getHostName()
    {
        static $_hostname = null;

        return
            $_hostname
                ?:
                ( $_hostname = isset( $_SERVER )
                    ? IfSet::get($_SERVER, 'HTTP_HOST', gethostname())
                    : gethostname()
                );
    }

    /**
     * @param array $map
     * @param array $paths
     *
     * @return string
     */
    protected static function _locateInstanceRootStorage(array $map, array $paths)
    {
        $_zone = trim(IfSet::get($map, 'zone'), DIRECTORY_SEPARATOR);
        $_partition = trim(IfSet::get($map, 'partition'), DIRECTORY_SEPARATOR);
        $_rootHash = trim(IfSet::get($map, 'root-hash'), DIRECTORY_SEPARATOR);

        if (empty( $_zone ) || empty( $_partition ) || empty( $_rootHash )) {
            return dirname(Pii::basePath()) . DIRECTORY_SEPARATOR . 'storage';
        }

        return implode(DIRECTORY_SEPARATOR, array($_zone, $_partition, $_rootHash));
    }

    /**
     * @param bool $setStatic If true (default), static::$_cacheKey will be set to the created key value
     *
     * @return string
     */
    private static function _makeCacheKey($setStatic = true)
    {
        $_key = 'dfe.config.' . static::_getHostName();

        $setStatic && ( static::$_cacheKey = $_key );

        return $_key;
    }

    /**
     * @param string $message
     *
     * @return bool|void
     */
    private static function debug($message)
    {
        return static::$debug && error_log($message . PHP_EOL, 3, static::$debugLogFile);
    }

    /**
     * Return a Laravel Database config for hosted instances or the default
     *
     * @return array|mixed
     */
    public static function getDatabaseConfig()
    {
        return static::isManagedInstance() ? static::getConfig('db') : [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', 'localhost'),
            'database'  => env('DB_DATABASE', 'forge'),
            'username'  => env('DB_USERNAME', 'forge'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ];
    }

    /**
     * Return the Policy Limits or an empty array if there are none.  Currently only API hit limits are supported
     *
     * @return array|mixed
     */
    public static function getPolicyLimits()
    {
        return static::isManagedInstace()? static::getConfig('limits') : [];
    }

    /**
     * Return the Console API Key hash or null
     *
     * @return string|null
     */
    public static function getConsoleKey()
    {
        if (static::isManagedInstance() === true) {
            $_env = static::getConfig('env');
            return sha256($_env['cluster-id'] . $_env['instance-id']);
        } else {
            return null;
        }
    }
}

Enterprise::initialize();