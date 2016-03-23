<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\Service;

class Package
{
    /** Packager version */
    const VERSION = '0.1';
    
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

    /** @type array  */
    protected $data = [];
    
    private static $metaFields = ['version', 'df_version', 'description', 'secured', 'created_date', 'storage'];

    public function __construct($packageInfo)
    {
        if(is_array($packageInfo)) {
            $this->manifest = $packageInfo;
            $this->dfVersion = $this->manifest['df_version'];
            $this->secured = array_get($this->manifest, 'secured', false);
            $this->createdDate = array_get($this->manifest, 'created_date', date('Y-m-d H:i:s', time()));
            $this->setManifestItems();
        }
    }
    
    public function isSecured()
    {
        return $this->secured;
    }
    
    public function getCreatedDate()
    {
        return $this->createdDate;
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

    public function isFileService($serviceName)
    {
        $service = Service::with('service_type_by_type')->whereName($serviceName)->first();
        $relations = $service->getRelation('service_type_by_type');
        $group = $relations->group;

        return ($group === 'File') ? true : false;
    }
    
    protected function isValid()
    {
        $m = $this->manifest;
        if (isset($m['version'], $m['df_version'])) {
            return true;
        }

        throw new InternalServerErrorException('Invalid package manifest supplied.');
    }

    protected function setManifestItems()
    {
        $m = $this->manifest;
        foreach ($m as $item => $value) {
            if (!in_array($item, static::$metaFields)) {
                if($this->isFileService($item)){
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
}