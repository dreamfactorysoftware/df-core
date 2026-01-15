<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that can handle requests
 */
interface RequestHandlerInterface
{
    /**
     * @param ServiceRequestInterface $request
     * @param null                    $resource
     *
     * @return ServiceResponseInterface
     */
    public function handleRequest(ServiceRequestInterface $request, $resource = null);
}
