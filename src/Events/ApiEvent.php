<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Utility\Session;

abstract class ApiEvent extends Event
{
    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string $path
     * @param mixed  $resource
     */
    public function __construct($path, $resource = null)
    {
        parent::__construct($path);
        $this->resource = $resource;
    }

    /**
     * @param string $name
     *
     * @return EventScript|null
     */
    public function getEventScript($name)
    {
        if (empty($model = EventScript::whereName($name)->whereIsActive(true)->first())) {
            return null;
        }

        $model->content = Session::translateLookups($model->content, true);
        if (!is_array($model->config)) {
            $model->config = [];
        }

        return $model;
    }

    public function makeData()
    {
        return [
            'resource' => $this->resource
        ];
    }
}
