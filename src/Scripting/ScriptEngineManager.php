<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Core\Contracts\ScriptEngineTypeInterface;
use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Events\Exceptions\ScriptException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Scripting\Engines\NodeJs;
use DreamFactory\Core\Scripting\Engines\Php;
use DreamFactory\Core\Scripting\Engines\Python;
use DreamFactory\Core\Scripting\Engines\V8Js;
use InvalidArgumentException;

/**
 * Scripting engine
 */
class ScriptEngineManager
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The cache TTL for the scripting store
     */
    const SESSION_STORE_TTL = 60;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of running script engines
     */
    protected static $instances = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The active engine instances.
     *
     * @var array
     */
    protected $engines = [];

    /**
     * The custom service resolvers.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * The custom script engine type information.
     *
     * @var ScriptEngineTypeInterface[]
     */
    protected $types = [];

    /**
     * Create a new script engine manager instance.
     *
     * @param  \Illuminate\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $types = [
            [
                'name'        => 'nodejs',
                'class_name'  => NodeJs::class,
                'label'       => 'Node.js',
                'description' => 'Server-side JavaScript handler using the Node.js engine.',
                'sandboxed'   => false
            ],
            [
                'name'        => 'php',
                'class_name'  => Php::class,
                'label'       => 'PHP',
                'description' => 'Script handler using native PHP.',
                'sandboxed'   => false
            ],
            [
                'name'        => 'python',
                'class_name'  => Python::class,
                'label'       => 'Python',
                'description' => 'Script handler using native Python.',
                'sandboxed'   => false
            ],
            [
                'name'        => 'v8js',
                'class_name'  => V8Js::class,
                'label'       => 'V8js',
                'description' => 'Server-side JavaScript handler using the V8js engine.',
                'sandboxed'   => true
            ]
        ];
        foreach ($types as $type) {
            $this->addType(new ScriptEngineType($type));
        }
    }

    /**
     * Get a service instance.
     *
     * @param  string $name
     *
     * @return ScriptEngineInterface
     */
    public function getService($name)
    {
        // If we haven't created this service, we'll create it based on the config provided.
        if (!isset($this->engines[$name])) {
            $service = $this->makeEngine($name);

//            if ($this->app->bound('events')) {
//                $connection->setEventDispatcher($this->app['events']);
//            }

            $this->engines[$name] = $service;
        }

        return $this->engines[$name];
    }

    public function getServiceById($id)
    {
        $name = Service::getCachedNameById($id);

        return $this->getService($name);
    }

    /**
     * Disconnect from the given service and remove from local cache.
     *
     * @param  string $name
     *
     * @return void
     */
    public function purge($name)
    {
        unset($this->services[$name]);
    }

    /**
     * Make the script engine instance.
     *
     * @param  string $name
     *
     * @return ScriptEngineInterface
     */
    protected function makeEngine($name)
    {
        $config = $this->getConfig($name);

        // First we will check by the service name to see if an extension has been
        // registered specifically for that service. If it has we will call the
        // Closure and pass it the config allowing it to resolve the service.
        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $config = $this->getDbConfig($name);
        $type = $config['type'];

        // Next we will check to see if a type extension has been registered for a service type
        // and will call the factory Closure if so, which allows us to have a more generic
        // resolver for the service types themselves which applies to all services.
        if (isset($this->types[$type])) {
            return $this->types[$type]->make($name, $config);
        }

        throw new InvalidArgumentException("Unsupported script engine type '$type'.");
    }

    /**
     * Get the configuration for a service.
     *
     * @param  string $name
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getConfig($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Service 'name' can not be empty.");
        }

        $services = $this->app['config']['df.service'];

        return array_get($services, $name);
    }

    /**
     * Get the configuration for a service.
     *
     * @param  string $name
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getDbConfig($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Service 'name' can not be empty.");
        }

        $config = Service::getCachedByName($name);

        return $config;
    }

    /**
     * Register a service type extension resolver.
     *
     * @param  ScriptEngineTypeInterface|null $type
     *
     * @return void
     */
    public function addType(ScriptEngineTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;
    }

    /**
     * Return the service type info.
     *
     * @param string $name
     *
     * @return ScriptEngineTypeInterface
     */
    public function getScriptEngineType($name)
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        return null;
    }

    /**
     * Return all of the known service types.
     *
     * @return ScriptEngineTypeInterface[]
     */
    public function getScriptEngineTypes()
    {
        return $this->types;
    }

    /**
     * Return all of the created services.
     *
     * @return array
     */
    public function getScriptEngines()
    {
        return $this->engines;
    }

    /**
     * @param string      $service
     * @param string      $verb
     * @param string|null $resource
     * @param array       $query
     * @param array       $header
     * @param null        $payload
     * @param string|null $format
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public function handleRequest(
        $service,
        $verb = Verbs::GET,
        $resource = null,
        $query = [],
        $header = [],
        $payload = null,
        $format = null
    ){
        $_FILES = []; // reset so that internal calls can handle other files.
        $request = new ServiceRequest();
        $request->setMethod($verb);
        $request->setParameters($query);
        $request->setHeaders($header);
        if (!empty($payload)) {
            if (is_array($payload)) {
                $request->setContent($payload);
            } elseif (empty($format)) {
                throw new BadRequestException('Payload with undeclared format.');
            } else {
                $request->setContent($payload, $format);
            }
        }

        $response = $this->getService($service)->handleRequest($request, $resource);

        if ($response instanceof ServiceResponseInterface) {
            return $response->getContent();
        } else {
            return $response;
        }
    }

    /**
     * Registers various available extensions to the v8 instance...
     *
     * @param array $engine_config
     * @param array $script_config
     *
     * @return ScriptingEngineInterface
     * @throws ServiceUnavailableException
     */
    public static function create(array $engine_config, $script_config = null)
    {
        $engineClass = array_get($engine_config, 'class_name');

        if (empty($engineClass) || !class_exists($engineClass)) {
            throw new ServiceUnavailableException("Failed to find script engine class '$engineClass'.");
        }

        $engine = new $engineClass($script_config);

        //  Stuff it in our instances array
        static::$instances[spl_object_hash($engine)] = $engine;

        return $engine;
    }

    /**
     * Publicly destroy engine
     *
     * @param ScriptingEngineInterface $engine
     */
    public static function destroy($engine)
    {
        $hash = spl_object_hash($engine);

        if (isset(static::$instances[$hash])) {
            unset(static::$instances[$hash]);
        }

        unset($engine);
    }

    /**
     * @param string $script        The script to run or a script file name
     * @param string $identifier    The name of this script
     * @param array  $engine_config The config of the script engine to run
     * @param array  $config        The config for this particular script
     * @param array  $data          The additional data as it will be exposed to script
     * @param string $output        Any output of the script
     *
     * @return array
     * @throws ScriptException
     * @throws ServiceUnavailableException
     */
    public static function runScript(
        $script,
        $identifier,
        array $engine_config,
        array $config = [],
        array &$data = [],
        &$output = null
    ){
        if (!empty($disable = config('df.scripting.disable'))) {
            switch (strtolower($disable)) {
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    $type = (isset($engine_config['name'])) ? $engine_config['name'] : null;
                    if (!empty($type) && (false !== stripos($disable, $type))) {
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        $engine = static::create($engine_config, $config);

        $result = $message = false;

        try {
            //  Don't show output
            ob_start();

            if (is_file($script)) {
                $result = $engine->executeScript($script, $identifier, $data, $config);
            } else {
                $result = $engine->executeString($script, $identifier, $data, $config);
            }
        } catch (ScriptException $ex) {
            $message = $ex->getMessage();

            \Log::error($message = "Exception executing script: $message");
        }

        //  Clean up
        $output = ob_get_clean();
        static::destroy($engine);

        if (boolval(\Config::get('df.log_script_memory_usage', false))) {
            \Log::debug('Engine memory usage: ' . static::resizeBytes(memory_get_usage(true)));
        }

        if (false !== $message) {
            throw new ScriptException($message, $output);
        }

        return $result;
    }

    /**
     * Converts single bytes into proper form (kb, gb, mb, etc.) with precision 2 (i.e. 102400 > 100.00kb)
     * Found on php.net's memory_usage page
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function resizeBytes($bytes)
    {
        static $units = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        /** @noinspection PhpIllegalArrayKeyTypeInspection */

        return @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), 2) . $units[$i];
    }
}
