<?php

namespace DreamFactory\Core\Services;

use Config;
use DreamFactory\Core\Components\LocalFileSystem;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Aws\Utility\AwsSvcUtilities;
use DreamFactory\Core\Aws\Components\S3FileSystem;
use DreamFactory\Core\Rackspace\Components\OpenStackObjectStorageSystem;
use DreamFactory\Core\Azure\Components\AzureBlobFileSystem;

class LocalFileService extends BaseFileService
{
    protected function setDriver($config)
    {
        $diskName = null;
        if (empty($config) || !isset($config['container'])) {
            $diskName = Config::get('filesystems.default');
        } else {
            $diskName = $config['container'];
        }

        if (empty($diskName)) {
            throw new InternalServerErrorException('Local file service driver/disk not configured. Please check configuration for file service - ' .
                $this->name .
                '.');
        }

        $disks = Config::get('filesystems.disks');

        if (!array_key_exists($diskName, $disks)) {
            throw new InternalServerErrorException('Local file service disk - ' .
                $diskName .
                ' not found.Please check configuration for file service - ' .
                $this->name .
                '.');
        }

        $disk = ArrayUtils::get($disks, $diskName);

        if (!isset($disk['driver'])) {
            throw new InternalServerErrorException('Mis-configured disk - ' . $diskName . '. Driver not specified.');
        }

        switch ($disk['driver']) {
            case 'local':
                $root = ArrayUtils::get($disk, 'root');

                if (empty($root)) {
                    throw new InternalServerErrorException('Mis-configured disk - ' .
                        $diskName .
                        '. Root path not specified.');
                }

                if (!is_dir($root)) {
                    throw new InternalServerErrorException('Mis-configured disk - ' .
                        $diskName .
                        '. Root path not found.');
                }

                $this->driver = new LocalFileSystem($root);

                break;
            case 's3':
                AwsSvcUtilities::updateCredentials($disk, false);
                $this->container = ArrayUtils::get($disk, 'bucket', ArrayUtils::get($disk, 'container'));
                ArrayUtils::set($disk, 'container', $this->container);

                if (empty($this->container)) {
                    throw new InternalServerErrorException('S3 file service bucket not specified. Please check configuration for file service - ' .
                        $this->name);
                }

                $this->driver = new S3FileSystem($disk);
                break;
            case 'rackspace':
                $this->container = ArrayUtils::get($disk, 'container');

                if (empty($this->container)) {
                    throw new InternalServerErrorException('Azure blob container not specified. Please check configuration for file service - ' .
                        $this->name);
                }

                $this->driver = new OpenStackObjectStorageSystem($disk);
                break;
            case 'azure':
                $this->container = ArrayUtils::get($disk, 'container');

                if (empty($this->container)) {
                    throw new InternalServerErrorException('Azure blob container not specified. Please check configuration for file service - ' .
                        $this->name);
                }
                $this->driver = new AzureBlobFileSystem($disk);
                break;
            default:
                break;
        }
    }
}