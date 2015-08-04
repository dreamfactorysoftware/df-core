<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;

/**
 * Class ScriptServiceRequest
 *
 */
class ScriptServiceRequest implements ServiceRequestInterface
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
        return ServiceRequestorTypes::SCRIPT;
    }
}