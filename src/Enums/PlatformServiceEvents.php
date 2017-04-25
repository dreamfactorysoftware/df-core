<?php
namespace DreamFactory\Core\Enums;


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
    const PRE_PROCESS = '{service.name}.{action}.pre_process';
    /**
     * @var string Called after the resource handler has processed the request
     */
    const POST_PROCESS = '{service.name}.{action}.post_process';
    /**
     * @var string Called after data has been formatted for caller but before send
     */
    const POST_DATA_FORMAT = '{service.name}.{action}.post_data_format';
}
