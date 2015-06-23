<?php

namespace DreamFactory\Core\Resources\System;

/**
 * Class EventScript
 *
 * @package DreamFactory\Core\Resources
 */
class EventScript extends BaseSystemResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Resource tag for dealing with event scripts
     */
    const RESOURCE_NAME = 'script';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);
        $this->model = 'DreamFactory\Core\Models\EventScript';
    }
}