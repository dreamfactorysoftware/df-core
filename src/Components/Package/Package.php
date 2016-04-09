<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Enums\ServiceTypeGroups;
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

    /** Password length for secured package */
    const PASSWORD_LENGTH = 6;

    /** @type array */
    protected $manifest = [];

    /** @type array */
    protected $storageItems = [];

    protected $nonStorageItems = [];

    /** @type string */
    protected $dfVersion = '';

    /** @type string */
    protected $createdDate = '';

    /** @type \ZipArchive null */
    protected $zip = null;

    /** @type array */
    protected $zipFile = null;

    /** @type string|null */
    protected $password = null;

    /**
     * Stores temp files to be deleted in __destruct.
     *
     * @type array
     */
    protected $destructible = [];

    /** @type bool */
    protected $deletePackageFile = true;

    /**
     * Package constructor.
     *
     * @param $packageInfo
     * @param $deletePackageFile
     */
    public function __construct($packageInfo = [], $deletePackageFile = true)
    {
        if (is_array($packageInfo) && $this->isUploadedFile($packageInfo)) {
            // Uploaded file. Import case.
            $this->manifest = $this->getManifestFromUploadedFile($packageInfo);
            $this->isValid();
        } elseif (is_array($packageInfo)) {
            // Supplied manifest. Export case.
            $this->manifest = $packageInfo;
        } elseif (is_string($packageInfo)) {
            if (is_file($packageInfo)) {
                $this->manifest = $this->getManifestFromLocalFile($packageInfo);
            } else {
                // URL imported file. Import case.
                $this->manifest = $this->getManifestFromUrlImport($packageInfo);
            }
            $this->isValid();
        }
        $this->setManifestItems();
        $this->deletePackageFile = $deletePackageFile;
    }

    /**
     * Cleans up all temp files.
     */
    public function __destruct()
    {
        if ($this->deletePackageFile) {
            @unlink($this->zipFile);
        }
        foreach ($this->destructible as $d) {
            @unlink($d);
        }
    }

    /**
     * Sets password for secured package.
     *
     * @param $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
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
            'secured'      => $this->isSecured(),
            'description'  => '',
            'created_date' => date('Y-m-d H:i:s', time())
        ];
    }

    /**
     * Gets the storage service from the manifest
     * to use for storing the exported zip file.
     *
     * @param $default
     *
     * @return mixed
     */
    public function getExportStorageService($default = null)
    {
        $storage = array_get($this->manifest, 'storage', $default);

        if (is_array($storage)) {
            $name = array_get($storage, 'name', array_get($storage, 'id', $default));
            if (is_numeric($name)) {
                $service = Service::find($name);

                return $service->name;
            }

            return $name;
        }

        return $storage;
    }

    /**
     * Gets the storage folder from the manifest
     * to use for storing the exported zip file in.
     *
     * @param $default
     *
     * @return string
     */
    public function getExportStorageFolder($default = null)
    {
        return array_get($this->manifest, 'storage.folder', array_get($this->manifest, 'storage.path', $default));
    }

    /**
     * Returns the filename for export file.
     *
     * @return string
     */
    public function getExportFilename()
    {
        $host = php_uname('n');
        $default = $host . '_' . date('Y-m-d_H:i:s', time());
        $filename = array_get(
            $this->manifest,
            'storage.filename',
            array_get($this->manifest, 'storage.file', $default)
        );

        if (strpos($filename, static::FILE_EXTENSION) === false) {
            $filename .= '.' . static::FILE_EXTENSION;
        }

        return $filename;
    }

    /**
     * Checks to see if packaged is secured.
     * Secured package has all its system/service config encrypted.
     *
     * @return bool
     */
    public function isSecured()
    {
        $secured = array_get($this->manifest, 'secured', false);

        return boolval($secured);
    }

    /**
     * Returns the password to use for encrypting/decrypting package.
     *
     * @return string|null
     * @throws BadRequestException
     */
    public function getPassword()
    {
        $password = array_get($this->manifest, 'password', $this->password);
        if ($this->isSecured()) {
            if (empty($password)) {
                throw new BadRequestException('Password is required for secured package.');
            } elseif (strlen($password) < static::PASSWORD_LENGTH) {
                throw new BadRequestException(
                    'Password must be at least ' . static::PASSWORD_LENGTH . ' characters long for secured package.'
                );
            }
        }

        return $password;
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
    public function isFileService($serviceName, $resources = null)
    {
        $service = Service::with('service_type_by_type')->whereName($serviceName)->first();
        if (!empty($service)) {
            $relations = $service->getRelation('service_type_by_type');
            $group = $relations->group;

            return ($group === ServiceTypeGroups::FILE) ? true : false;
        } elseif (!empty($resources)) {
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
     * @throws InternalServerErrorException
     */
    protected function isValid()
    {
        $m = $this->manifest;
        if (isset($m['version'], $m['df_version'])) {
            return true;
        }

        throw new InternalServerErrorException('Invalid package supplied.');
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
            $this->zipFile = FileUtilities::importUrlFileToTemp($url);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to import package from $url. " . $ex->getMessage());
        }

        return $this->getManifestFromZipFile();
    }

    /**
     * Retrieves manifest from a local zip file.
     * 
     * @param $file
     *
     * @return array|string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function getManifestFromLocalFile($file)
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (static::FILE_EXTENSION != $extension) {
            throw new BadRequestException(
                "Only package files ending with '" .
                static::FILE_EXTENSION .
                "' are allowed for import."
            );
        }

        if (file_exists($file)) {
            $this->zipFile = $file;
        } else {
            throw new InternalServerErrorException("Failed to import. File not found $file");
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

        if (!empty($m)) {
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
    }

    /**
     * Initialize export zip file.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function initZipFile()
    {
        $filename = $this->getExportFilename();
        $zip = new \ZipArchive();
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $zipFileName = $tmpDir . $filename;
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
     * Adds content to ZipArchive.
     *
     * @param $localFilename
     * @param $content
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function zipContent($localFilename, $content)
    {
        if (!$this->zip->addFromString($localFilename, $content)) {
            throw new InternalServerErrorException("Failed to add $localFilename to Zip Archive");
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
     * Retrieves a file from ZipArchive.
     *
     * @param $file
     *
     * @return null|string
     */
    public function getFileFromZip($file)
    {
        if (false !== $content = $this->zip->getFromName($file)) {
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $fileName = $tmpDir . md5($file) . time() . '.' . pathinfo($file, PATHINFO_EXTENSION);
            file_put_contents($fileName, $content);

            $this->destructible[] = $fileName;

            return $fileName;
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