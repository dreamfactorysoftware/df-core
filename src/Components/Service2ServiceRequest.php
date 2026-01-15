<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\Verbs;

/**
 * Class Service2ServiceRequest
 *
 */
class Service2ServiceRequest extends InternalServiceRequest implements ServiceRequestInterface
{
    /**
     * @var int, see ServiceRequestorTypes
     */
    protected $requestorType = ServiceRequestorTypes::API; // for now, maybe independent type later

    public function __construct($method = Verbs::GET, $parameters = [], $headers = [])
    {
        $this->setMethod($method);
        $this->setParameters($parameters);
        $this->setHeaders($headers);
    }
}