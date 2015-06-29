<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\ApiDocManager;

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
        return ApiDocManager::getProcessEventMap();
    }
}