<?php
namespace DreamFactory\Core\Testing;

use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Services\BaseRestService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Artisan;
use ServiceManager;

class TestCase extends LaravelTestCase
{
    /**
     * A flag to make sure that the stage() method gets to run one time only.
     *
     * @var bool
     */
    protected static $staged = false;

    /** @var string resource array wrapper */
    protected static $wrapper = null;

    /**
     * Provide the service id/name that you want to run
     * the test cases on.
     *
     * @var mixed null
     */
    protected $serviceId = null;

    /** @var BaseRestService null */
    protected $service = null;

    /**
     * Runs before every test class.
     */
    public static function setupBeforeClass(): void
    {
        echo "\n------------------------------------------------------------------------------\n";
        echo "Running test: " . get_called_class() . "\n";
        echo "------------------------------------------------------------------------------\n\n";
    }

    /**
     * Runs before every test.
     */
    public function setUp() :void
    {
        parent::setUp();

        Model::unguard(false);

        if (false === static::$staged) {
            $this->stage();
            static::$staged = true;
        }

        $this->setService();

        $config = $this->app->make('Config');
        static::$wrapper = $config::get('df.resources_wrapper');
    }

    /**
     * Sets up the service based on
     */
    protected function setService()
    {
        if (!empty($this->serviceId)) {
            if (is_numeric($this->serviceId)) {
                $this->service = static::getServiceById($this->serviceId);
            } else {
                $this->service = static::getService($this->serviceId);
            }
        }
    }

    /**
     * This method is used for staging the overall
     * test environment. Which usually covers things like
     * running database migrations and seeders.
     *
     * In order to override and run this method on a child
     * class, you must set the static::$staged property to
     * false in the respective child class.
     */
    public function stage()
    {
        Artisan::call('migrate');
        Artisan::call('db:seed');
        Model::unguard();

        // Add default admin user
        if (!User::exists()) {
            User::create(
                [
                    'name'         => 'DF Admin',
                    'email'        => 'admin@test.com',
                    'password'     => 'Dream123!',
                    'is_sys_admin' => true,
                    'is_active'    => true
                ]
            );
        }
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require './bootstrap/app.php';

        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        return $app;
    }

    /**
     * @param $verb
     * @param $url
     * @param $payload
     *
     * @return \Illuminate\Http\Response
     */
    protected function callWithPayload($verb, $url, $payload)
    {
        $rs = $this->call($verb, $url, [], [], [], [], $payload);

        return $rs;
    }

    /**
     * Checks to see if a service already exists
     *
     * @param string $serviceName
     *
     * @return bool
     */
    protected function serviceExists($serviceName)
    {
        return Service::whereName($serviceName)->exists();
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public static function getService($name)
    {
        return ServiceManager::getService($name);
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public static function getServiceById($id)
    {
        return ServiceManager::getServiceById($id);
    }

    /**
     * @param       $verb
     * @param       $resource
     * @param array $query
     * @param null  $payload
     * @param array $header
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface
     */
    protected function makeRequest($verb, $resource = null, $query = [], $payload = null, $header = [])
    {
        $request = new TestServiceRequest($verb, $query, $header);
        $request->setApiVersion('v1');

        if (!empty($payload)) {
            if (is_array($payload)) {
                $request->setContent($payload);
            } else {
                $request->setContent($payload, DataFormats::JSON);
            }
        }

        return $this->handleRequest($request, $resource);
    }

    /**
     * @param TestServiceRequest $request
     * @param null               $resource
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface
     * @throws InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function handleRequest(TestServiceRequest $request, $resource = null)
    {
        if (empty($this->service)) {
            throw new InternalServerErrorException('No service is setup to process request on. Please set the serviceId. It can be an Id or Name.');
        }

        return $this->service->handleRequest($request, $resource);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Returns non-public class properties
     *
     * @param $object
     * @param $property
     *
     * @return mixed
     */
    public function getNonPublicProperty(&$object, $property)
    {
        $reflection = new \ReflectionProperty(get_class($object), $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    /**
     * Returns test instance url
     *
     * @return mixed
     */
    public function getBaseUrl()
    {
        return env('TEST_INSTANCE_URL', 'http://localhost');
    }

    /**
     * Returns system temp directory
     *
     * @return string
     */
    public function getTempDir()
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Returns temp file
     *
     * @param $file
     *
     * @return string
     */
    public function getTempFile($file, $content = 'Temp File'){
        $file = $this->getTempDir() . $file;
        file_put_contents($file, $content);

        return $file;
    }
}
