<?php
namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Services\BaseRestService;

class BaseDbResource extends BaseRestResource
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var integer Service identifier
     */
    protected $serviceId = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param RequestHandlerInterface $parent
     */
    public function setParent(RequestHandlerInterface $parent)
    {
        parent::setParent($parent);

        /** @var BaseRestService $parent */
        $this->serviceId = $parent->getServiceId();
    }

    /**
     * @param null $schema
     * @param bool $refresh
     *
     * @return array
     */
    public function listAccessComponents($schema = null, $refresh = false)
    {
        return [];
    }
}