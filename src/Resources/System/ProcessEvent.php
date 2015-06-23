<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Services\Swagger;

/**
 * Class ProcessEvent
 *
 * @package DreamFactory\Core\Resources
 */
class ProcessEvent extends BaseEvent
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Resource tag for listing events that interrupt processing
     */
    const RESOURCE_NAME = 'process';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    protected function getEventMap()
    {
        return Swagger::getProcessEventMap();
    }
}