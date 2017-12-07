<?php
namespace DreamFactory\Core\Testing;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Contracts\ServiceRequestInterface;

/**
 * Class TestServiceRequest
 *
 */
class TestServiceRequest extends InternalServiceRequest implements ServiceRequestInterface
{
    public function __construct($method = Verbs::GET, $parameters = [], $headers = [], $payload = [])
    {
        $this->setMethod($method);
        $this->setParameters($parameters);
        $this->setHeaders($headers);
        $this->setPayloadData($payload);
    }
}