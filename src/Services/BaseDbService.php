<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Resources\BaseDbResource;

abstract class BaseDbService extends BaseRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of resource defining arrays
     */
    protected $resources = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array
     */
    protected function getResources()
    {
        return $this->resources;
    }

    // REST service implementation

    /**
     * {@inheritdoc}
     */
    public function listResources($fields = null)
    {
        $refresh = $this->request->getParameterAsBool('refresh');
        $schema = $this->request->getParameter('schema', '');

        if (!empty($fields) || (!$this->request->getParameterAsBool('as_access_components'))) {
            return parent::listResources($fields);
        }

        $output = [];
        $access = $this->getPermissions();
        if (!empty($access)) {
            $output[] = '';
            $output[] = '*';
        }
        foreach ($this->resources as $resourceInfo) {
            $className = $resourceInfo['class_name'];

            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $this->resourcePath);
            }

            /** @var BaseDbResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);
            $access = $this->getPermissions($resource->name);
            if (!empty($access)) {
                $output[] = $resource->name . '/';
                $output[] = $resource->name . '/*';

                $results = $resource->listAccessComponents($schema, $refresh);
                $output = array_merge($output, $results);
            }
        }

        return ['resource' => $output];
    }

    /**
     * {@InheritDoc}
     */
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $apis = [
            [
                'path'        => '/' . $this->name,
                'description' => 'Operations available for system resources.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getResourceList() - List only the available resource names.',
                        'nickname'         => 'getResourceList',
                        'notes'            => 'List the resource names available in this service.',
                        'type'             => 'ComponentList',
                        'event_name'       => [$this->name . '.list'],
                        'parameters'       => [
                            [
                                'name'          => 'as_access_components',
                                'description'   => 'Return the names of all the accessible components.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'default'       => false,
                            ],
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the resource list.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getResources() - List all resources.',
                        'nickname'         => 'getResources',
                        'notes'            => 'List the resources available on this service. ',
                        'type'             => 'Resources',
                        'event_name'       => [$this->name . '.list'],
                        'parameters'       => [
                            [
                                'name'          => 'fields',
                                'description'   => 'Return all or specified properties available for each resource.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => true,
                                'default'       => '*',
                            ],
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the resource list.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                ],
            ],
        ];

        $base['apis'] = array_merge($base['apis'], $apis);

        return $base;
    }
}