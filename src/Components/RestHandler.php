<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Contracts\ResourceInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Events\ApiEvent;
use DreamFactory\Core\Events\PostProcessApiEvent;
use DreamFactory\Core\Events\PreProcessApiEvent;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class RestHandler
 *
 * @package DreamFactory\Core\Components
 */
abstract class RestHandler implements RequestHandlerInterface
{
    use ExceptionResponse, HasApiDocs;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const ACTION_TOKEN = '{action}';
    /**
     * @var string The default pattern of dispatch methods. Action token embedded.
     */
    const DEFAULT_HANDLER_PATTERN = 'handle{action}';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string API name
     */
    protected $name;
    /**
     * @var string Description of this service
     */
    protected $label;
    /**
     * @var string Description of this service
     */
    protected $description;
    /**
     * @var string HTTP Action Verb
     */
    protected $action = Verbs::GET;
    /**
     * @var string HTTP Action Verb
     */
    protected $originalAction = null;
    /**
     * @var string Resource name.
     */
    protected $resource;
    /**
     * @var mixed Resource ID.
     */
    protected $resourceId;
    /**
     * @var string Resource Path.
     */
    protected $resourcePath;
    /**
     * @var array Resource path exploded into array.
     */
    protected $resourceArray;
    /**
     * @var bool If true, processRequest() dispatches a call to handle[Action]() methods if defined.
     * For example, a GET request would be dispatched to handleGet().
     */
    protected $autoDispatch = true;
    /**
     * @var string The pattern to search for dispatch methods.
     * The string {action} will be replaced by the inbound action (i.e. Get, Put, Post, etc.)
     */
    protected $autoDispatchPattern = self::DEFAULT_HANDLER_PATTERN;
    /**
     * @var bool|array Array of verb aliases. Has no effect if $autoDispatch !== true
     *
     * Example:
     *
     * $this->verbAliases = array(
     *     static::Put => static::Post,
     *     static::Patch => static::Post,
     *     static::Merge => static::Post,
     *
     *     // Use a closure too!
     *     static::Get => function($resource){
     *    ...
     *   },
     * );
     *
     *    The result will be that processRequest() will dispatch a PUT, PATCH, or MERGE request to the POST handler.
     */
    protected $verbAliases = [];
    /**
     * @var ServiceRequestInterface Request object implementing the ServiceRequestInterface.
     */
    protected $request = null;
    /**
     * @var ServiceResponseInterface Response object implementing the ServiceResponseInterface.
     */
    protected $response = null;

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        foreach ($settings as $key => $value) {
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = camel_case($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            }
            // set real and virtual
            $this->{$key} = $value;
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param ServiceRequestInterface $request
     * @param string|null             $resource
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface
     */
    public function handleRequest(ServiceRequestInterface $request, $resource = null)
    {
        $this->setRequest($request);
        $this->setAction($request->getMethod());
        $this->setResourceMembers($resource);
        $this->response = null;

        $resources = $this->getResourceHandlers();
        if (!empty($resources) && !empty($this->resource)) {
            try {
                if (false === $this->response = $this->handleResource($resources)) {
                    $message = ucfirst($this->action) . " requests for resource '{$this->resourcePath}' are not currently supported by the '{$this->name}' service.";
                    throw new BadRequestException($message);
                }

                if (!($this->response instanceof ServiceResponseInterface ||
                    $this->response instanceof RedirectResponse ||
                    $this->response instanceof StreamedResponse
                )
                ) {
                    $this->response = ResponseFactory::create($this->response);
                }
            } catch (\Exception $e) {
                $this->response = static::exceptionToServiceResponse($e);
            }

            return $this->response;
        }

        try {
            //  Perform any pre-processing
            $this->preProcess();

            // pre-process can now create a response along with throw exceptions to circumvent the processRequest
            if (null === $this->response) {
                if (false === $this->response = $this->processRequest()) {
                    $message = ucfirst($this->action) . " requests without a resource are not currently supported by the '{$this->name}' service.";
                    throw new BadRequestException($message);
                }
            }

            if (!($this->response instanceof ServiceResponseInterface ||
                $this->response instanceof RedirectResponse ||
                $this->response instanceof StreamedResponse
            )
            ) {
                $this->response = ResponseFactory::create($this->response);
            }
        } catch (\Exception $e) {
            $this->response = static::exceptionToServiceResponse($e);
        }

        //  Perform any post-processing
        try {
            $this->postProcess();
        } catch (\Exception $e) {
            // override the actual response with the exception
            $this->response = static::exceptionToServiceResponse($e);
        }

        //  Perform any response processing
        return $this->respond();
    }

    /**
     * @param array $resources
     *
     * @return bool|mixed
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    protected function handleResource(array $resources)
    {
        $found = array_by_key_value($resources, 'name', $this->resource);
        if (!isset($found, $found['class_name'])) {
            throw new NotFoundException("Resource '{$this->resource}' not found for service '{$this->name}'.");
        }

        $className = $found['class_name'];

        if (!class_exists($className)) {
            throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                $this->resourcePath);
        }

        /** @var ResourceInterface $resource */
        $resource = $this->instantiateResource($className, $found);

        $newPath = $this->resourceArray;
        array_shift($newPath);
        $newPath = implode('/', $newPath);

        return $resource->handleRequest($this->request, $newPath);
    }

    protected function instantiateResource($class, $info = [])
    {
        /** @var ResourceInterface $obj */
        $obj = new $class($info);
        $obj->setParent($this);

        return $obj;
    }

    protected function getEventName()
    {
        return $this->name;
    }

    protected function getEventResource()
    {
        return $this->resourcePath;
    }

    /**
     * Fires pre process event
     * @param string|null $name     Optional override for name
     * @param string|null $resource Optional override for resource
     */
    protected function firePreProcessEvent($name = null, $resource = null)
    {
        if (empty($name)) {
            $name = $this->getEventName();
        }
        if (empty($resource)) {
            $resource = $this->getEventResource();
        }
        $event = new PreProcessApiEvent($name, $this->request, $this->response, $resource);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::dispatch($event);
        $this->response = $event->response;
    }

    /**
     * Runs pre processing tasks
     */
    protected function preProcess()
    {
        $this->firePreProcessEvent();
    }

    /**
     * @return bool|mixed
     * @throws BadRequestException
     */
    protected function processRequest()
    {
        //	Now all actions must be HTTP verbs
        if (!Verbs::contains($this->action)) {
            throw new BadRequestException('The action "' . $this->action . '" is not supported.');
        }

        $methodToCall = false;

        //	Check verb aliases as closures
        if (true === $this->autoDispatch && null !== ($alias = array_get($this->verbAliases, $this->action))) {
            //	A closure?
            if (!in_array($alias, Verbs::getDefinedConstants()) && is_callable($alias)) {
                $methodToCall = $alias;
            }
        }

        //  Not an alias, build a dispatch method if needed
        if (!$methodToCall) {
            //	If we have a dedicated handler method, call it
            $method = str_ireplace(static::ACTION_TOKEN, $this->action, $this->autoDispatchPattern);

            if ($this->autoDispatch && method_exists($this, $method)) {
                $methodToCall = [$this, $method];
            }
        }

        if ($methodToCall) {
            $result = call_user_func($methodToCall);

            if (false === $result ||
                $result instanceof ServiceResponseInterface ||
                $result instanceof RedirectResponse ||
                $result instanceof StreamedResponse
            ) {
                return $result;
            }

            return ResponseFactory::create($result);
        }

        //	Otherwise just return false
        return false;
    }

    /**
     * Fires post process event
     * @param string|null $name     Optional name to append
     * @param string|null $resource Optional override for resource
     */
    protected function firePostProcessEvent($name = null, $resource = null)
    {
        if (empty($name)) {
            $name = $this->getEventName();
        }
        if (empty($resource)) {
            $resource = $this->getEventResource();
        }
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::dispatch(new PostProcessApiEvent($name, $this->request, $this->response, $resource));
    }

    /**
     * Runs post process tasks
     */
    protected function postProcess()
    {
        $this->firePostProcessEvent();
    }

    /**
     * Fires last event before responding
     * @param string|null $name     Optional name to append
     * @param string|null $resource Optional override for resource
     */
    protected function fireFinalEvent($name = null, $resource = null)
    {
        if (empty($name)) {
            $name = $this->getEventName();
        }
        if (empty($resource)) {
            $resource = $this->getEventResource();
        }
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::dispatch(new ApiEvent($name, $this->request, $this->response, $resource));
    }

    /**
     * @return ServiceResponseInterface
     */
    protected function respond()
    {
        if (!($this->response instanceof ServiceResponseInterface ||
            $this->response instanceof RedirectResponse ||
            $this->response instanceof StreamedResponse
        )
        ) {
            $this->response = ResponseFactory::create($this->response);
        }

        $this->fireFinalEvent();

        return $this->response;
    }

    /**
     * Sets the request object
     *
     * @param $request ServiceRequestInterface
     *
     * @return $this
     */
    protected function setRequest(ServiceRequestInterface $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Sets the HTTP Action verb
     *
     * @param $action string
     *
     * @return $this
     */
    protected function setAction($action)
    {
        $this->action = trim(strtoupper($action));

        //	Check verb aliases, set correct action allowing for closures
        if (null !== ($alias = array_get($this->verbAliases, $this->action))) {
            //	A closure?
            if (in_array($alias, Verbs::getDefinedConstants()) || !is_callable($alias)) {
                //	Set original and work with alias
                $this->originalAction = $this->action;
                $this->action = $alias;
            }
        }

        return $this;
    }

    /**
     * @return string The action actually requested
     */
    public function getRequestedAction()
    {
        return $this->originalAction ?: $this->action;
    }

    /**
     * @param string $action
     *
     * @return $this
     */
    public function overrideAction($action)
    {
        $this->action = trim(strtoupper($action));

        return $this;
    }

    /**
     * @return string
     */
    public function getOriginalAction()
    {
        return $this->originalAction;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Apply the commonly used REST path members to the class.
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function setResourceMembers($resourcePath = null)
    {
        // remove trailing slash here, override this function if you need it
        $this->resourcePath = rtrim($resourcePath, '/');
        $this->resourceArray = (!empty($this->resourcePath)) ? explode('/', $this->resourcePath) : [];

        if (!empty($this->resourceArray)) {
            $resource = array_get($this->resourceArray, 0);
            if (!is_null($resource) && ('' !== $resource)) {
                $this->resource = $resource;
            }
            $id = array_get($this->resourceArray, 1);
            if (!is_null($id) && ('' !== $id)) {
                $this->resourceId = $id;
            }
        }

        return $this;
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return mixed
     */
    protected function getPayloadData($key = null, $default = null)
    {
        $data = $this->request->getPayloadData($key, $default);

        return $data;
    }

    /**
     * Implement to return the resource configuration for this REST handling object
     *
     * @return array Empty when not implemented, otherwise the array of resource information
     */
    public function getResources()
    {
        return [];
    }

    /**
     * Implement to return the resource handler configuration for this REST handling object
     *
     * @return array Empty when not implemented, otherwise the array of resource handlers
     */
    protected function getResourceHandlers()
    {
        return [];
    }

    /**
     * Returns the identifier of the supported resources
     *
     * @return string
     * @throws BadRequestException
     */
    protected static function getResourceIdentifier()
    {
        throw new BadRequestException('No known identifier for resources.');
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @return bool
     */
    public function checkPermission(
        /** @noinspection PhpUnusedParameterInspection */
        $operation,
        $resource = null
    ) {
        return false;
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public function getPermissions(
        /** @noinspection PhpUnusedParameterInspection */
        $resource = null
    ) {
        return false;
    }

    /**
     * Handles GET action
     *
     * @return mixed
     * @throws BadRequestException
     */
    protected function handleGET()
    {
        $resources = $this->getResources();
        if (is_array($resources)) {
            $includeAccess = $this->request->getParameterAsBool(ApiOptions::INCLUDE_ACCESS);
            $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
            $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
            $fields = $this->request->getParameter(ApiOptions::FIELDS);
            if (!$asList && $includeAccess) {
                foreach ($resources as &$resource) {
                    if (is_array($resource)) {
                        $name = array_get($resource, $idField);
                        $resource['access'] =
                            VerbsMask::maskToArray($this->getPermissions($name));
                    }
                }
            }

            return ResourcesWrapper::cleanResources($resources, $asList, $idField, $fields);
        }

        return $resources;
    }

    /**
     * Handles POST action
     *
     * @return mixed
     */
    protected function handlePOST()
    {
        return false;
    }

    /**
     * Handles PUT action
     *
     * @return mixed
     */
    protected function handlePUT()
    {
        return false;
    }

    /**
     * Handles PATCH action
     *
     * @return mixed
     */
    protected function handlePATCH()
    {
        return false;
    }

    /**
     * Handles DELETE action
     *
     * @return mixed
     */
    protected function handleDELETE()
    {
        return false;
    }
}