<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Models\EventScript as EventScriptModel;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class EventScript extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = EventScriptModel::class;
}