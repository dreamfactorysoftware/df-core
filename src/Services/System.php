<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Models\SystemResource;

class System extends BaseRestService
{
    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        return SystemResource::all()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $list = parent::getAccessList();
        $nameField = $this->getResourceIdentifier();
        foreach ($this->getResources() as $resource)
        {
            $name = ArrayUtils::get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = $name . '/';
                $list[] = $name . '/*';
            }
        }

        return $list;
    }
}