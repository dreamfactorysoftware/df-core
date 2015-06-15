<?php
namespace DreamFactory\Core\Events\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * These events are thrown by service handlers before and after resource has been processed
 */
class PlatformServiceEvents extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string Called before the resource request is dispatched
     */
    const PRE_PROCESS = '{api_name}.{action}.pre_process';
    /**
     * @var string Called after the resource handler has processed the request
     */
    const POST_PROCESS = '{api_name}.{action}.post_process';
    /**
     * @var string Called after data has been formatted for caller but before send
     */
    const AFTER_DATA_FORMAT = '{api_name}.{action}.after_data_format';
}
