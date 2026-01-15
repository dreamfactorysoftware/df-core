<?php
namespace DreamFactory\Core\Components;

trait HasApiDocs
{
    public function getApiDocInfo()
    {
        $base = [];
        if (!empty($paths = $this->getApiDocPaths())) {
            $base['paths'] = $paths;
        }
        if (!empty($parameters = $this->getApiDocParameters())) {
            $base['components']['parameters'] = $parameters;
        }
        if (!empty($requestBodies = $this->getApiDocRequests())) {
            $base['components']['requestBodies'] = $requestBodies;
        }
        if (!empty($schemas = $this->getApiDocSchemas())) {
            $base['components']['schemas'] = $schemas;
        }
        if (!empty($responses = $this->getApiDocResponses())) {
            $base['components']['responses'] = $responses;
        }

        return $base;
    }

    protected function getApiDocPaths()
    {
        return [];
    }

    protected function getApiDocParameters()
    {
        return [];
    }

    protected function getApiDocRequests()
    {
        return [];
    }

    protected function getApiDocResponses()
    {
        return [];
    }

    protected function getApiDocSchemas()
    {
        return [];
    }
}