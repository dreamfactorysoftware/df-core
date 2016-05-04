<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Core\Contracts\ScriptEngineTypeInterface;
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

    //*************************************************************************
    //	Methods
    //*************************************************************************

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
                'label'       => 'Node.js',
                'description' => 'Server-side JavaScript handler using the Node.js engine.',
                'sandboxed'   => false,
                'factory'     => function ($config){
                    return new NodeJs($config);
                },
            ],
            [
                'name'        => 'php',
                'label'       => 'PHP',
                'description' => 'Script handler using native PHP.',
                'sandboxed'   => false,
                'factory'     => function ($config){
                    return new Php($config);
                },
            ],
            [
                'name'        => 'python',
                'label'       => 'Python',
                'description' => 'Script handler using native Python.',
                'sandboxed'   => false,
                'factory'     => function ($config){
                    return new Python($config);
                },
            ],
            [
                'name'        => 'v8js',
                'label'       => 'V8js',
                'description' => 'Server-side JavaScript handler using the V8js engine.',
                'sandboxed'   => true,
                'factory'     => function ($config){
                    return new V8Js($config);
                },
            ]
        ];
        foreach ($types as $type) {
            $this->addType(new ScriptEngineType($type));
        }
    }

    /**
     * Get the configuration for a script engine.
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
            throw new InvalidArgumentException("Script Engine 'name' can not be empty.");
        }

        $engines = $this->app['config']['df.script'];

        return array_get($engines, $name, []);
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
     * Make the script engine instance.
     *
     * @param string $type
     * @param array  $script_config
     *
     * @return \DreamFactory\Core\Contracts\ScriptingEngineInterface
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    public function makeEngine($type, array $script_config = [])
    {
        if (!empty($disable = config('df.scripting.disable'))) {
            switch (strtolower($disable)) {
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    if (!empty($type) && (false !== stripos($disable, $type))) {
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        $config = $this->getConfig($type);

        // Next we will check to see if a type extension has been registered for a engine type
        // and will call the factory Closure if so, which allows us to have a more generic
        // resolver for the engine types themselves which applies to all scripting.
        if (isset($this->types[$type])) {
            return $this->types[$type]->make(array_merge($config, $script_config));
        }

        throw new InvalidArgumentException("Unsupported script engine type '$type'.");
    }
}
