<?php

namespace DreamFactory\Core\Services;

use Fruitcake\Cors\CorsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DfCorsService extends CorsService
{
    /** @var string[] */
    private array $allowedMethodsCopy = [];


    public function setOptions(array $options): void
    {
        $this->allowedMethodsCopy = $options['allowedMethods'] ?? $options['allowed_methods'] ?? $this->allowedMethodsCopy;
        parent::setOptions($options);
    }
    
    public function handlePreflightRequest(Request $request): Response
    {
        $response = new Response();

        $requestMethod = strtoupper($request->headers->get('Access-Control-Request-Method'));
        if(!in_array($requestMethod, $this->allowedMethodsCopy)) {
            $response->setStatusCode(405, 'Method not allowed');
        } else {
            $response->setStatusCode(204);
        }
        
        return $this->addPreflightRequestHeaders($response, $request);
    }
}
