<?php

namespace DreamFactory\Core\Http\Controllers;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\ServiceRequest;
use DreamFactory\Core\Utility\Session;
use Log;
use ServiceManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class RestController
 *
 * @package DreamFactory\Core\Http\Controllers
 */
class RestController extends Controller
{
    /**
     * Handles the root (/) path
     *
     * @param null|string $version
     *
     * @return null|ServiceResponseInterface|Response
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function index($version = null)
    {
        try {
            $request = new ServiceRequest();
            if (!empty($version)) {
                $request->setApiVersion($version);
            }

            Log::info('[REQUEST]', [
                'API Version' => $request->getApiVersion(),
                'Method'      => $request->getMethod(),
                'Service'     => null,
                'Resource'    => null
            ]);

            Log::debug('[REQUEST]', [
                'Parameters' => json_encode($request->getParameters(), JSON_UNESCAPED_SLASHES),
                'API Key'    => $request->getHeader('X_DREAMFACTORY_API_KEY'),
                'JWT'        => $request->getHeader('X_DREAMFACTORY_SESSION_TOKEN')
            ]);

            $limitedFields = ['id', 'name', 'label', 'description', 'type'];
            $fields = \Request::query(ApiOptions::FIELDS);
            if (!empty($fields) && !is_array($fields)) {
                $fields = array_map('trim', explode(',', trim($fields, ',')));
            } elseif (empty($fields)) {
                $fields = $limitedFields;
            }
            $fields = array_intersect($fields, $limitedFields);

            $group = \Request::query('group');
            $type = \Request::query('type');
            if (!empty($group)) {
                $results = ServiceManager::getServiceListByGroup($group, $fields, true);
            } elseif (!empty($type)) {
                $results = ServiceManager::getServiceListByType($type, $fields, true);
            } else {
                $results = ServiceManager::getServiceList($fields, true);
            }
            $services = [];
            foreach ($results as $info) {
                // only allowed services by role here
                if (Session::allowsServiceAccess(array_get($info, 'name'))) {
                    $services[] = $info;
                }
            }

            if (!empty($group)) {
                $results = ServiceManager::getServiceTypes($group);
            } elseif (!empty($type)) {
                $results = [ServiceManager::getServiceType($type)];
            } else {
                $results = ServiceManager::getServiceTypes();
            }
            $types = [];
            foreach ($results as $type) {
                $types[] = [
                    'name'        => $type->getName(),
                    'label'       => $type->getLabel(),
                    'group'       => $type->getGroup(),
                    'description' => $type->getDescription()
                ];
            }
            $response = ResponseFactory::create(['services' => $services, 'service_types' => $types]);
            Log::info('[RESPONSE]', ['Status Code' => $response->getStatusCode(), 'Content-Type' => $response->getContentType()]);

            return ResponseFactory::sendResponse($response, $request);
        } catch (\Exception $e) {
            return ResponseFactory::sendException($e, (isset($request) ? $request : null));
        }
    }

    /**
     * Handles all service requests
     *
     * @param null|string $version
     * @param string      $service
     * @param null|string $resource
     *
     * @return ServiceResponseInterface|Response|null
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function handleVersionedService($version, $service, $resource = null)
    {
        $request = new ServiceRequest();
        $request->setApiVersion($version);

        return $this->handleServiceRequest($request, $service, $resource);
    }

    /**
     * Handles all service requests
     *
     * @param string      $service
     * @param null|string $resource
     *
     * @return ServiceResponseInterface|Response|null
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function handleService($service, $resource = null)
    {
        $request = new ServiceRequest();

        return $this->handleServiceRequest($request, $service, $resource);
    }

    /**
     * @param ServiceRequest $request
     * @param                $service
     * @param null           $resource
     * @return array|ServiceResponseInterface|mixed|string|Response
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    protected function handleServiceRequest(ServiceRequest $request, $service, $resource = null)
    {
        try {
            $service = strtolower($service);

            // fix removal of trailing slashes from resource
            if (!empty($resource)) {
                $resource = str_replace(['..'], '', $resource);
                $uri = \Request::getRequestUri();
                if ((false === strpos($uri, '?') && '/' === substr($uri, strlen($uri) - 1, 1)) ||
                    ('/' === substr($uri, strpos($uri, '?') - 1, 1))
                ) {
                    $resource .= '/';
                }
            }

            $response = ServiceManager::handleServiceRequest($request, $service, $resource, false);
            if (($response instanceof RedirectResponse) || ($response instanceof StreamedResponse)) {
                return $response;
            }

            return ResponseFactory::sendResponse($response, $request, null, $resource);
        } catch (\Exception $e) {
            return ResponseFactory::sendException($e, $request);
        }
    }
}
