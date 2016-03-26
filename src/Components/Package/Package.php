<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\DataFormatter;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Services\BaseFileService;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Resources\System\Environment;

/**
 * Class Package.
 * This class represents the package fle.
 * It encompasses all package data and actions.
 *
 * @package DreamFactory\Core\Components\Package
 */
class Package
{
    /** Packager version */
    const VERSION = '0.1';

    /** Import file extension */
    const FILE_EXTENSION = 'zip';

    /** @type array */
    protected $manifest = [];

    /** @type array */
    protected $storageItems = [];

    protected $nonStorageItems = [];

    /** @type string */
    protected $dfVersion = '';

    /** @type bool */
    protected $secured = false;

    /** @type string */
    protected $createdDate = '';

    /** @type \ZipArchive null */
    protected $zip = null;

    /** @type array */
    protected $zipFile = null;

    /**
     * Stores temp files to be deleted in __destruct.
     *
     * @type array
     */
    protected $destructible = [];

    /**
     * Package constructor.
     *
     * @param $packageInfo
     */
    public function __construct($packageInfo)
    {
        if (is_array($packageInfo) && $this->isUploadedFile($packageInfo)) {
            // Uploaded file. Import case.
            $this->manifest = $this->getManifestFromUploadedFile($packageInfo);
            $this->isValid();
        } else if (is_array($packageInfo)) {
            // Supplied manifest. Export case.
            $this->manifest = $packageInfo;
        } else if (is_string($packageInfo)) {
            // URL imported file. Import case.
            $this->manifest = $this->getManifestFromUrlImport($packageInfo);
            $this->isValid();
        }
        $this->setManifestItems();
    }

    /**
     * Cleans up all temp files.
     */
    public function __destruct()
    {
        @unlink($this->zipFile);
        foreach ($this->destructible as $d) {
            @unlink($d);
        }
    }

    /**
     * Returns standard manifest header.
     *
     * @return array
     */
    public function getManifestHeader()
    {
        return [
            'version'      => static::VERSION,
            'df_version'   => config('df.version'),
            'secured'      => $this->secured,
            'description'  => '',
            'created_date' => date('Y-m-d H:i:s', time())
        ];
    }

    /**
     * Returns all manifest items.
     *
     * @return mixed
     */
    public function getServices()
    {
        return array_merge($this->nonStorageItems, $this->storageItems);
    }

    /**
     * Returns non-storage manifest items.
     *
     * @return array
     */
    public function getNonStorageServices()
    {
        return $this->nonStorageItems;
    }

    /**
     * Returns storage manifest items only.
     *
     * @return array
     */
    public function getStorageServices()
    {
        return $this->storageItems;
    }

    /**
     * Checks to see if a service is a file/storage service.
     *
     * @param $serviceName
     * @param $resources
     *
     * @return bool
     */
    public function isFileService($serviceName, $resources)
    {
        $service = Service::with('service_type_by_type')->whereName($serviceName)->first();
        if (!empty($service)) {
            $relations = $service->getRelation('service_type_by_type');
            $group = $relations->group;

            return ($group === 'File') ? true : false;
        } else {
            if (is_string($resources)) {
                $resources = explode(',', $resources);
            }
            foreach ($resources as $resource) {
                if (is_string($resource)) {
                    if (false !== $this->zip->locateName(
                            $serviceName .
                            '/' .
                            rtrim($resource, '/') .
                            '/' .
                            md5($resource) .
                            '.' .
                            static::FILE_EXTENSION
                        )
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks package validity.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function isValid()
    {
        $m = $this->manifest;
        if (isset($m['version'], $m['df_version'])) {
            return true;
        }

        throw new InternalServerErrorException('Invalid package manifest supplied.');
    }

    /**
     * Checks for valid uploaded file.
     *
     * @param array $package
     *
     * @return bool
     */
    protected function isUploadedFile($package)
    {
        if (isset($package['name'], $package['tmp_name'], $package['type'], $package['size'])) {
            if ('application/zip' === $package['type']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the manifest from uploaded package file.
     *
     * @param $package
     *
     * @return array|string
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function getManifestFromUploadedFile($package)
    {
        $this->zipFile = $package['tmp_name'];

        return $this->getManifestFromZipFile();
    }

    /**
     * Returns the manifest from url imported package file.
     *
     * @param $url
     *
     * @return array|string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function getManifestFromUrlImport($url)
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        if (static::FILE_EXTENSION != $extension) {
            throw new BadRequestException(
                "Only package files ending with '" .
                static::FILE_EXTENSION .
                "' are allowed for import."
            );
        }

        try {
            // need to download and extract zip file and move contents to storage
            $this->zipFile = FileUtilities::importUrlFileToTemp($url);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to import package $url. {$ex->getMessage()}");
        }

        return $this->getManifestFromZipFile();
    }

    /**
     * Retrieves the manifest file from package file.
     *
     * @return array|string
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function getManifestFromZipFile()
    {
        $this->zip = new \ZipArchive();
        if (true !== $this->zip->open($this->zipFile)) {
            throw new InternalServerErrorException('Failed to open imported zip file.');
        }

        $manifest = $this->zip->getFromName('package.json');
        if (false === $manifest) {
            throw new InternalServerErrorException('No package.json file found in the imported zip file.');
        }
        $manifest = DataFormatter::jsonToArray($manifest);

        return $manifest;
    }

    /**
     * Sets manifest items as class property.
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function setManifestItems()
    {
        $m = $this->manifest;
        if (isset($m['service']) && is_array($m['service'])) {
            foreach ($m['service'] as $item => $value) {
                if ($this->isFileService($item, $value)) {
                    $this->storageItems[$item] = $value;
                } else {
                    $this->nonStorageItems[$item] = $value;
                }
            }
        }

        if (count($this->getServices()) == 0) {
            throw new InternalServerErrorException('No items found in package manifest.');
        }
    }

    /**
     * Initialize export zip file.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function initZipFile()
    {
        $host = php_uname('n');
        $filename = $host . '_' . date('Y-m-d_H:i:s', time());
        $zip = new \ZipArchive();
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $zipFileName = $tmpDir . $filename . '.' . static::FILE_EXTENSION;
        $this->zip = $zip;
        $this->zipFile = $zipFileName;

        if (true !== $this->zip->open($zipFileName, \ZipArchive::CREATE)) {
            throw new InternalServerErrorException('Failed to initiate package Zip Archive.');
        }

        return true;
    }

    /**
     * Adds manifest file to ZipArchive.
     *
     * @param $manifest
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function zipManifestFile($manifest)
    {
        if (!$this->zip->addFromString('package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES))) {
            throw new InternalServerErrorException('Failed to add manifest file to the Zip Archive.');
        }
    }

    /**
     * Adds resource file to ZipArchive.
     *
     * @param $file
     * @param $resource
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function zipResourceFile($file, $resource)
    {
        if (!$this->zip->addFromString($file, json_encode($resource, JSON_UNESCAPED_SLASHES))) {
            throw new InternalServerErrorException("Failed to add $file to the Zip Archive.");
        }
    }

    /**
     * Adds a file to ZipArchive.
     *
     * @param $file
     * @param $newFile
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function zipFile($file, $newFile)
    {
        if (!$this->zip->addFile($file, $newFile)) {
            throw new InternalServerErrorException("Failed to add $newFile to Zip Archive");
        }
    }

    /**
     * Retrieves resource data from ZipArchive.
     *
     * @param $resourceFile
     *
     * @return array
     */
    public function getResourceFromZip($resourceFile)
    {
        $data = [];
        $json = $this->zip->getFromName($resourceFile);
        if (false !== $json) {
            $data = json_decode($json, JSON_UNESCAPED_SLASHES);
        }

        return $data;
    }

    /**
     * Retrieves zipped folder from ZipArchive.
     *
     * @param $file
     *
     * @return null|\ZipArchive
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function getZipFromZip($file)
    {
        $fh = $this->zip->getStream($file);
        if (false !== $fh) {
            $contents = null;
            while (!feof($fh)) {
                $contents .= fread($fh, 2);
            }
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $zipFile = $tmpDir . md5($file) . time() . '.' . static::FILE_EXTENSION;
            file_put_contents($zipFile, $contents);

            $zip = new \ZipArchive();
            if (true !== $zip->open($zipFile)) {
                throw new InternalServerErrorException('Error opening zip file ' . $file . '.');
            }
            $this->destructible[] = $zipFile;

            return $zip;
        }

        return null;
    }

    /**
     * Saves ZipArchive.
     *
     * @param $storageService
     * @param $storageFolder
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function saveZipFile($storageService, $storageFolder)
    {
        try {
            $this->zip->close();

            /** @type BaseFileService $storage */
            $storage = ServiceHandler::getService($storageService);
            $container = $storage->getContainerId();
            if (!$storage->driver()->folderExists($container, $storageFolder)) {
                $storage->driver()->createFolder($container, $storageFolder);
            }
            $storage->driver()->moveFile(
                $container,
                $storageFolder . '/' . basename($this->zipFile),
                $this->zipFile
            );
            $url = Environment::getURI() .
                '/' .
                $storageService .
                '/' .
                $storageFolder .
                '/' .
                basename($this->zipFile);

            return $url;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to save the exported package using storage service ' .
                $storageService . '. ' .
                $e->getMessage()
            );
        }
    }
}