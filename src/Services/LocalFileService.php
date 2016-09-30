<?php

namespace DreamFactory\Core\Services;

use Config;
use DreamFactory\Core\Aws\Components\S3FileSystem;
use DreamFactory\Core\Azure\Components\AzureBlobFileSystem;
use DreamFactory\Core\Components\LocalFileSystem;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Rackspace\Components\OpenStackObjectStorageSystem;
use DreamFactory\Core\Utility\Session;

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

        //  Replace any private lookups
        Session::replaceLookups($diskName, true);
        if (empty($diskName)) {
            throw new InternalServerErrorException('Local file service folder/disk not configured.' .
                ' Please check configuration for file service - ' . $this->name . '.');
        }

        $disks = Config::get('filesystems.disks');
        if (empty($disk = array_get($disks, $diskName))) {
            $disk = ['driver' => 'local', 'root' => $diskName];
        } else {
            //  Replace any private lookups
            Session::replaceLookups($disk, true);

            if (!isset($disk['driver'])) {
                throw new InternalServerErrorException('Mis-configured disk - ' . $diskName . '. Driver not specified.');
            }
        }

        switch ($disk['driver']) {
            case 'local':
                $root = $disk['root'];

                if (empty($root)) {
                    throw new InternalServerErrorException('Mis-configured disk - ' .
                        $diskName .
                        '. Root path not specified.');
                }

                if (!is_dir($root)) {
                    mkdir($root, 0775);
                }

                if (!is_dir($root)) {
                    throw new InternalServerErrorException('Mis-configured disk - ' .
                        $diskName .
                        '. Root path not found.');
                }

                $this->driver = new LocalFileSystem($root);

                break;
            case 's3':
                $this->container = array_get($disk, 'bucket', array_get($disk, 'container'));
                $disk['container'] = $this->container;

                if (empty($this->container)) {
                    throw new InternalServerErrorException('S3 file service bucket/container not specified. Please check configuration for file service - ' .
                        $this->name);
                }

                $this->driver = new S3FileSystem($disk);
                break;
            case 'rackspace':
                $this->container = array_get($disk, 'container');

                if (empty($this->container)) {
                    throw new InternalServerErrorException('Azure blob container not specified. Please check configuration for file service - ' .
                        $this->name);
                }

                $this->driver = new OpenStackObjectStorageSystem($disk);
                break;
            case 'azure':
                $this->container = array_get($disk, 'container');

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
