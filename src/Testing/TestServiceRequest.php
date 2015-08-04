<?php
namespace DreamFactory\Core\Testing;

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use \Exception;

/**
 * Class TestServiceRequest
 *
 */
class TestServiceRequest implements ServiceRequestInterface
{
    use InternalServiceRequest;

    /**
     * @var int, see ServiceRequestorTypes
     */
    protected $requestorType = ServiceRequestorTypes::API;

    public function __construct($method = Verbs::GET, $parameters = [], $headers = [])
    {
        $this->setMethod($method);
        $this->setParameters($parameters);
        $this->setHeaders($headers);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestorType()
    {
        return $this->requestorType;
    }

    /**
     * @param integer $type , see ServiceRequestorTypes
     *
     * @throws Exception
     */
    public function setRequestorType($type)
    {
        if (ServiceRequestorTypes::contains($type)) {
            $this->requestorType = $type;
        }

        throw new Exception('Invalid service requestor type provided.');
    }
}