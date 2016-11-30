<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostProcessApiEvent extends InterProcessApiEvent
{
    public $request;

    public $response;

    /**
     * Create a new event instance.
     *
     * @param string                   $path
     * @param ServiceRequestInterface  $request
     * @param ServiceResponseInterface $response
     * @param mixed                    $resource
     */
    public function __construct($path, $request, &$response, $resource = null)
    {
        $this->request = $request;
        $this->response = $response;
        $name = strtolower($path . '.' . $request->getMethod()) . '.post_process';
        parent::__construct($name, $resource);
    }

    public function makeData()
    {
        return [
            'request'  => $this->request->toArray(),
            'resource' => $this->resource,
            'response' => ($this->response instanceof RedirectResponse || $this->response instanceof StreamedResponse)
                ? [] : $this->response->toArray()
        ];
    }
}
