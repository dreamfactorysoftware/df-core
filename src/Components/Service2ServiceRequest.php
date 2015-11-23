<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Library\Utility\Enums\Verbs;

/**
 * Class Service2ServiceRequest
 *
 */
class Service2ServiceRequest implements ServiceRequestInterface
{
    use InternalServiceRequest;

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
        return ServiceRequestorTypes::API; // for now, maybe independent type later
    }
}