<?php

namespace DreamFactory\Core\Components\Package;

use DreamFactory\Core\Exceptions\InternalServerErrorException;

class Package
{
    /** Packager version */
    const VERSION = '0.1';
    
    /** @type array  */
    protected $manifest = [];

    /** @type array */
    protected $items = [];

    /** @type string */
    protected $dfVersion = '';

    /** @type bool */
    protected $secured = false;

    /** @type string */
    protected $createdDate = '';

    /** @type array  */
    protected $data = [];
    
    private static $metaFields = ['version', 'df_version', 'description', 'secured', 'created_date'];

    public function __construct($packageInfo)
    {
        if(is_array($packageInfo)) {
            $this->manifest = $packageInfo;
            $this->isValid();
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
        return $this->items;
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
                $this->items[$item] = $value;
            }
        }

        if (count($this->items) == 0) {
            throw new InternalServerErrorException('No items found in package manifest.');
        }
    }
}