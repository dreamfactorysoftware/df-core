<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Models\InstanceId;
use DreamFactory\Core\Enums\LicenseLevel;
use Illuminate\Validation\ValidationException;
use ServiceManager;
use Validator;

class Environment
{
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
     * Returns the instance ID
     * 
     * @return string
     */
    public static function getDreamFactoryInstanceId()
    {
        $instanceID = '';
        try {
            $instanceID = InstanceId::getCachedInstanceId();
        } catch (\Exception $e) {
            $instanceID = "Not Found";
        }
        return $instanceID;
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

    /**
     * Returns instance's URI
     *
     * @return string
     */
    public static function getURI()
    {
        $s = $_SERVER;

        if (self::isUnderProxy($s)) {
            return self::getProxyURI($s);
        }

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
     * Determines whether the server is behind a proxy
     *
     * @param null|array $server
     * @return bool
     */
    public static function isUnderProxy($server = null) {
        if (empty($server)) {
            $server = $_SERVER;
        }
        return !empty($server['HTTP_X_FORWARDED_PROTO']) && !empty($server['HTTP_X_FORWARDED_PORT']) && !empty($server['HTTP_X_FORWARDED_HOST']);
    }

    /**
     * Return server URI provided by the proxy or null if not supported
     *
     * @param null|array $server
     * @return string|false
     */
    public static function getProxyURI($server = null) {
        if (empty($server)) {
            $server = $_SERVER;
        }
        if (!self::isUnderProxy($server)) {
            return false;
        }
        return $server['HTTP_X_FORWARDED_PROTO'] . '://' . $server['HTTP_X_FORWARDED_HOST'] . ':' . $server['HTTP_X_FORWARDED_PORT'];
    }

    /**
     * Parses the data coming back from phpinfo() call and returns in an array
     *
     * @return array
     */
    public static function getPhpInfo()
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
     * Retrieve product code from AWS Metadata Server if server available
     *
     * See https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-data-retrieval.html
     *
     * @return string|null
     */
    /*public static function getProductCode() {
        return \Cache::remember('aws-product-code', \Config::get('df.default_cache_ttl'),
            function () {
                $metaDataParser = new AWSMetaDataParser();
                if ($metaDataParser->getToken()) {
                    return $metaDataParser->getProductCode();
                }
                return null; // we are not on AWS instance
            });
    }*/

    /**
     * Retrieve instance id from AWS Metadata Server if server available
     *
     * See https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-data-retrieval.html
     *
     * @return string|null
     */
    public static function getInstanceId() {
        return \Cache::remember('aws-instance-id', \Config::get('df.default_cache_ttl'),
            function () {
                $metaDataParser = new AWSMetaDataParser();
                if ($metaDataParser->getToken()) {
                    return $metaDataParser->getInstanceId();
                }
                return null; // we are not on AWS instance
            });
    }

    /**
     * @param array $info
     *
     * @param bool  $recursive
     *
     * @return array
     */
    public static function cleanPhpInfo($info, $recursive = false)
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
                    $v1 = '';
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
}
