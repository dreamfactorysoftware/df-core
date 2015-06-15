<?php

namespace DreamFactory\Core\Services;

use Config;
use DreamFactory\Core\Components\LocalFileSystem;
use DreamFactory\Library\Utility\ArrayUtils;

class LocalFileService extends BaseFileService
{
    protected function setDriver($config)
    {
        if (empty($config) || !isset($config['root'])) {
            $root = storage_path() . "/" . env('LOCAL_FILE_ROOT');
        } else {
            $root = ArrayUtils::get($config, "root");
        }

        $this->driver = new LocalFileSystem($root);
    }
}