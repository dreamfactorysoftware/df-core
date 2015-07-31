<?php
namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Services\BaseRestService;

class BaseDbResource extends BaseRestResource
{
    const RESOURCE_IDENTIFIER = 'name';
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
     * {@inheritdoc}
     */
    protected function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

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
    public function listAccessComponents(
        /** @noinspection PhpUnusedParameterInspection */
        $schema = null, $refresh = false)
    {
        return [];
    }
}