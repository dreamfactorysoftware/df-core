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

class Package
{
    /** Packager version */
    const VERSION = '0.1';
    
    const FILE_EXTENSION = 'zip';
    
    /** @type array  */
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

    /** @type \ZipArchive null  */
    protected $zip = null;
    
    /** @type array  */
    protected $zipFile = null;


    
    private static $metaFields = ['version', 'df_version', 'description', 'secured', 'created_date', 'storage'];

    public function __construct($packageInfo)
    {
        if(is_array($packageInfo) && $this->isUploadedFile($packageInfo)){
            $this->manifest = $this->getManifestFromUploadedFile($packageInfo);
        } else if(is_array($packageInfo)) {
            $this->manifest = $packageInfo;
        } else if(is_string($packageInfo)){
            $this->manifest = $this->getManifestFromUrlImport($packageInfo);
        }
        $this->setManifestItems();
    }

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
    
    public function getItems()
    {
        return array_merge($this->nonStorageItems, $this->storageItems);
    }
    public function getNonStorageItems()
    {
        return $this->nonStorageItems;
    }
    
    public function getStorageItems()
    {
        return $this->storageItems;
    }

    public function isFileService($serviceName, $resources)
    {
        $service = Service::with('service_type_by_type')->whereName($serviceName)->first();
        if(!empty($service)) {
            $relations = $service->getRelation('service_type_by_type');
            $group = $relations->group;

            return ($group === 'File') ? true : false;
        } else {
            if(is_string($resources)){
                $resources = explode(',', $resources);
            }
            foreach ($resources as $resource){
                if(is_string($resource)) {
                    if (false !== $this->zip->locateName(
                            $serviceName . '/' . rtrim($resource, '/') . '/' . md5($resource) . '.zip'
                        )
                    ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    protected function isValid()
    {
        $m = $this->manifest;
        if (isset($m['version'], $m['df_version'])) {
            return true;
        }

        throw new InternalServerErrorException('Invalid package manifest supplied.');
    }

    protected function isUploadedFile($package)
    {
        if(isset($package['name'], $package['tmp_name'], $package['type'], $package['size'])){
            if('application/zip' === $package['type']) {
                return true;
            }
        }
        return false;
    }

    protected function getManifestFromUploadedFile($package)
    {
        $this->zipFile = $package['tmp_name'];
        return $this->getManifestFromZipFile();
    }

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

    protected function getManifestFromZipFile()
    {
        $this->zip = new \ZipArchive();
        if(true !== $this->zip->open($this->zipFile))
        {
            throw new InternalServerErrorException('Failed to open imported zip file.');
        }

        $manifest = $this->zip->getFromName('package.json');
        if(false === $manifest){
            throw new InternalServerErrorException('No package.json file found in the imported zip file.');
        }
        $manifest = DataFormatter::jsonToArray($manifest);

        return $manifest;
    }

    protected function setManifestItems()
    {
        $m = $this->manifest;
        foreach ($m as $item => $value) {
            if (!in_array($item, static::$metaFields)) {
                if($this->isFileService($item, $value)){
                    $this->storageItems[$item] = $value;
                } else {
                    $this->nonStorageItems[$item] = $value;
                }
            }
        }

        if (count($this->getItems()) == 0) {
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
        $zipFileName = $tmpDir . $filename . '.zip';
        $this->zip = $zip;
        $this->zipFile = $zipFileName;

        if (true !== $this->zip->open($zipFileName, \ZipArchive::CREATE)) {
            throw new InternalServerErrorException('Failed to initiate package Zip Archive.');
        }

        return true;
    }

    public function zipManifestFile($manifest)
    {
        if (!$this->zip->addFromString('package.json', json_encode($manifest, JSON_UNESCAPED_SLASHES))) {
            throw new InternalServerErrorException('Failed to add manifest file to the Zip Archive.');
        }
    }
    
    public function zipResourceFile($file, $resource)
    {
        if (!$this->zip->addFromString($file, json_encode($resource, JSON_UNESCAPED_SLASHES))) {
            throw new InternalServerErrorException("Failed to add $file to the Zip Archive.");
        }
    }
    
    public function zipFile($file, $newFile)
    {
        if (!$this->zip->addFile($file, $newFile)) {
            throw new InternalServerErrorException("Failed to add $newFile to Zip Archive");
        }
    }
    
    public function getResourceFromZip($resourceFile)
    {
        $data = [];
        $json = $this->zip->getFromName($resourceFile);
        if(false !== $json){
            $data = json_decode($json, JSON_UNESCAPED_SLASHES);
        }
        return $data;
    }

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