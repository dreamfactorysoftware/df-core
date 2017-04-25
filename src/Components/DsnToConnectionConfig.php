<?php
namespace DreamFactory\Core\Components;


trait DsnToConnectionConfig
{
    public static function adaptConfig(array $entry, &$newType)
    {
        $dsn = array_get($entry, 'dsn');
        $driver = array_get($entry, 'driver');
        $newType = $driver;
        $config = [];
        switch ($driver) {
            case 'ibm':
                $newType = 'ibmdb2';
                $config = static::adaptDsn($dsn);
                break;
            case 'oci':
            case 'oracle':
                $newType = 'oracle';
                if (!empty($dsn)) {
                    $dsn = str_replace(' ', '', $dsn);
                    // traditional connection string uses (), reset find
                    if (false !== ($pos = stripos($dsn, 'host='))) {
                        $temp = substr($dsn, $pos + 5);
                        $config['host'] =
                            (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
                    }
                    if (false !== ($pos = stripos($dsn, 'port='))) {
                        $temp = substr($dsn, $pos + 5);
                        $config['port'] =
                            (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
                    }
                    if (false !== ($pos = stripos($dsn, 'sid='))) {
                        $temp = substr($dsn, $pos + 4);
                        $config['database'] =
                            (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
                    }
                    if (false !== ($pos = stripos($dsn, 'service_name='))) {
                        $temp = substr($dsn, $pos + 13);
                        $config['service_name'] =
                            (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
                    }
                }
                break;
            case 'sqlite':
                if (!empty($dsn)) {
                    // default PDO DSN pieces
                    $dsn = str_replace(' ', '', $dsn);
                    $file = substr($dsn, 7);
                    $config['database'] = $file;
                }
                break;
            case 'dblib':
            case 'sqlsrv':
                $newType = 'sqlsrv';
                if (!empty($dsn)) {
                    // default PDO DSN pieces
                    $config = static::adaptDsn($dsn);
                    // SQL Server native driver specifics
                    if (!isset($config['host']) && (false !== ($pos = stripos($dsn, 'Server=')))) {
                        $temp = substr($dsn, $pos + 7);
                        $host = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                        if (!isset($config['port']) && (false !== ($pos = stripos($host, ',')))) {
                            $temp = substr($host, $pos + 1);
                            $host = substr($host, 0, $pos);
                            $config['port'] =
                                (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                        }
                        $config['host'] = $host;
                    }
                    if (!isset($config['database']) &&
                        (false !== ($pos = stripos($dsn, 'Database=')))
                    ) {
                        $temp = substr($dsn, $pos + 9);
                        $config['database'] =
                            (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                    }
                }
                break;
            case 'sqlanywhere':
            case 'pgsql':
            case 'mysql':
                $config = static::adaptDsn($dsn);
                break;
            default:
                return null;
        }
        $config['username'] = array_get($entry, 'username');
        $config['password'] = array_get($entry, 'password');
        if (array_get_bool($entry, 'default_schema_only')) {
            $config['default_schema_only'] = true;
        }

        return $config;
    }

    public static function adaptDsn($dsn = '')
    {
        if (empty($dsn)) {
            return [];
        }

        $config = [];
        // default PDO DSN pieces
        $dsn = str_replace(' ', '', $dsn);
        if (false !== ($pos = strpos($dsn, 'port='))) {
            $temp = substr($dsn, $pos + 5);
            $config['port'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
        }
        if (false !== ($pos = strpos($dsn, 'host='))) {
            $temp = substr($dsn, $pos + 5);
            $host = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            if (!isset($config['port']) && (false !== ($pos = stripos($host, ':')))) {
                $temp = substr($host, $pos + 1);
                $host = substr($host, 0, $pos);
                $config['port'] = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
            $config['host'] = $host;
        }
        if (false !== ($pos = strpos($dsn, 'dbname='))) {
            $temp = substr($dsn, $pos + 7);
            $config['database'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
        }
        if (false !== ($pos = strpos($dsn, 'charset='))) {
            $temp = substr($dsn, $pos + 8);
            $config['charset'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
        } else {
            $config['charset'] = 'utf8';
        }

        return $config;
    }
}