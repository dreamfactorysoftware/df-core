<?php
namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\DbSchemaExtras;
use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Services\BaseDbService;

abstract class BaseDbResource extends BaseRestResource
{
    use DbSchemaExtras;

    const RESOURCE_IDENTIFIER = 'name';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var integer Service identifier
     */
    protected $serviceId = null;
    /**
     * @var BaseDbService
     */
    protected $parent = null;

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

        /** @var BaseDbService $parent */
        $this->serviceId = $parent->getServiceId();
    }

    /**
     * @return string
     */
    abstract public function getResourceName();

    /**
     * @param null $schema
     * @param bool $refresh
     *
     * @return array
     */
    abstract public function listResources(
        /** @noinspection PhpUnusedParameterInspection */
        $schema = null,
        $refresh = false
    );

    /**
     * @param null $schema
     * @param bool $refresh
     *
     * @return array
     */
    public function listAccessComponents($schema = null, $refresh = false)
    {
        $output = [];
        $result = $this->listResources($schema, $refresh);
        foreach ($result as $name) {
            $output[] = $this->getResourceName() . '/' . $name;
        }

        return $output;
    }

}