<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Scripting\Models\NodejsConfig;
use DreamFactory\Core\Scripting\Models\PhpConfig;
use DreamFactory\Core\Scripting\Models\PythonConfig;
use DreamFactory\Core\Scripting\Models\V8jsConfig;
use DreamFactory\Core\Scripting\Services\Nodejs;
use DreamFactory\Core\Scripting\Services\Php;
use DreamFactory\Core\Scripting\Services\Python;
use DreamFactory\Core\Scripting\Services\V8js;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use Illuminate\Support\ServiceProvider;

class ScriptingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // The script engine manager is used to resolve various script engines.
        // It also implements the resolver interface which may be used by other components adding script engines.
        $this->app->singleton('df.script', function ($app){
            return new ScriptEngineManager($app);
        });

        // Add our scripting service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType(
                    [
                        'name'           => 'nodejs',
                        'label'          => 'Node.js',
                        'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'          => ServiceTypeGroups::SCRIPT,
                        'config_handler' => NodejsConfig::class,
                        'factory'        => function ($config){
                            return new Nodejs($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'           => 'php',
                        'label'          => 'PHP',
                        'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'          => ServiceTypeGroups::SCRIPT,
                        'config_handler' => PhpConfig::class,
                        'factory'        => function ($config){
                            return new Php($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'           => 'python',
                        'label'          => 'Python',
                        'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'          => ServiceTypeGroups::SCRIPT,
                        'config_handler' => PythonConfig::class,
                        'factory'        => function ($config){
                            return new Python($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'           => 'v8js',
                        'label'          => 'V8js',
                        'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'          => ServiceTypeGroups::SCRIPT,
                        'config_handler' => V8jsConfig::class,
                        'factory'        => function ($config){
                            return new V8js($config);
                        },
                    ]));
        });
    }
}
