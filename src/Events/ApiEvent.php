<?php

namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiEvent extends ServiceEvent
{
    public $request;

    public $response;

    /**
     * Create a new event instance.
     *
     * @param string                        $path
     * @param ServiceRequestInterface       $request
     * @param ServiceResponseInterface|null $response
     * @param mixed                         $resource
     */
    public function __construct($path, $request, $response = null, $resource = null)
    {
        $this->request = $request;
        $this->response = $response;
        $name = $path . '.' . strtolower($request->getMethod());
        parent::__construct($name, $resource);
    }

    public function makeData()
    {
        $response = (empty($this->response) || $this->response instanceof RedirectResponse ||
            $this->response instanceof StreamedResponse)
            ? [] : $this->response->toArray();

        return array_merge(parent::makeData(),
            [
                'request'  => $this->request->toArray(),
                'response' => $response
            ]
        );
    }
}
