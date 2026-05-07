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
        $this->allowedMethodsCopy = $this->normalizeAllowedMethods(
            $options['allowedMethods'] ?? $options['allowed_methods'] ?? $this->allowedMethodsCopy
        );

        parent::setOptions($options);
    }
    
    public function handlePreflightRequest(Request $request): Response
    {
        $response = new Response();

        $requestMethod = strtoupper((string)$request->headers->get('Access-Control-Request-Method'));
        if(!$this->isMethodAllowed($requestMethod)) {
            $response->setStatusCode(405, 'Method not allowed');
        } else {
            $response->setStatusCode(204);
        }
        
        return $this->addPreflightRequestHeaders($response, $request);
    }

    protected function isMethodAllowed(string $requestMethod): bool
    {
        return in_array('*', $this->allowedMethodsCopy, true) ||
            in_array($requestMethod, $this->allowedMethodsCopy, true);
    }

    protected function normalizeAllowedMethods($methods): array
    {
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        return array_map(static function ($method) {
            return strtoupper(trim((string)$method));
        }, $methods);
    }
}
