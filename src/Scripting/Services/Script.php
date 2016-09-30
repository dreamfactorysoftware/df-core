<?php
namespace DreamFactory\Core\Scripting\Services;

use DreamFactory\Core\Components\ScriptHandler;
use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Jobs\ScriptServiceJob;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Scalar;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Log;

/**
 * Script
 * Scripting as a Service
 */
class Script extends BaseRestService
{
    use ScriptHandler, DispatchesJobs;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string $content Content of the script
     */
    protected $content;
    /**
     * @var string $engineType Type of the script
     */
    protected $engineType;
    /**
     * @var array $scriptConfig Configuration for the engine for this particular script
     */
    protected $scriptConfig;
    /**
     * @var boolean $queued Configuration for the engine for this particular script
     */
    protected $queued = false;
    /**
     * @var array
     */
    protected $apiDoc = [];
    /**
     * @type bool
     */
    protected $implementsAccessList = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new Script Service
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        $config = (array)array_get($settings, 'config');
        Session::replaceLookups($config, true);

        if (!is_string($this->content = array_get($config, 'content'))) {
            $this->content = '';
        }

        $this->queued = Scalar::boolval(array_get($config, 'queued', false));

        if (empty($this->engineType = array_get($config, 'type'))) {
            throw new \InvalidArgumentException('Script engine configuration can not be empty.');
        }

        if (!is_array($this->scriptConfig = array_get($config, 'config', []))) {
            $this->scriptConfig = [];
        }

        $this->apiDoc = (array)array_get($settings, 'doc');
        $this->implementsAccessList = boolval(array_get($config, 'implements_access_list', false));
    }

    /**
     * Returns all request data.
     *
     * @return array
     */
    protected function getRequestData()
    {
        return [
            'request'  => $this->request->toArray(),
            'response' => [
                'content'      => null,
                'content_type' => null,
                'status_code'  => ServiceResponseInterface::HTTP_OK
            ],
            'resource' => $this->resourcePath
        ];
    }

    public function getAccessList()
    {
        $list = parent::getAccessList();

        $paths = array_keys((array)array_get($this->apiDoc, 'paths'));
        foreach ($paths as $path) {
            // drop service from path
            if (!empty($path = ltrim(strstr(ltrim($path, '/'), '/'), '/'))) {
                $list[] = $path;
                $path = explode("/", $path);
                end($path);
                while ($level = prev($path)) {
                    $list[] = $level . '/*';
                }
            }
        }

        natsort($list);

        return array_values(array_unique($list));
    }

    /**
     * @return bool|mixed
     * @throws
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    protected function processRequest()
    {
        if (!$this->implementsAccessList &&
            (Verbs::GET === $this->action) &&
            ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST))
        ) {
            $result = ResourcesWrapper::wrapResources($this->getAccessList());

            return ResponseFactory::create($result);
        }

        if ($this->queued) {
            $job = new ScriptServiceJob($this->getServiceId(), $this->request, $this->resourcePath);
            $result = $this->dispatch($job);
            Log::debug('API service script queued: ' . $this->name . PHP_EOL . $result);

            return ResponseFactory::create(['success' => true], null, HttpStatusCodeInterface::HTTP_ACCEPTED);
        }

        $data = $this->getRequestData();

        $logOutput = $this->request->getParameterAsBool('log_output', true);
        $result = $this->handleScript('service.' . $this->name, $this->content, $this->engineType, $this->scriptConfig,
            $data, $logOutput);

        if (is_array($result) && array_key_exists('response', $result)) {
            $result = array_get($result, 'response', []);
        }

        if (is_array($result) && array_key_exists('content', $result)) {
            $content = array_get($result, 'content');
            $contentType = array_get($result, 'content_type');
            $status = array_get($result, 'status_code', HttpStatusCodeInterface::HTTP_OK);

            return ResponseFactory::create($content, $contentType, $status);
        }

        // otherwise assume raw content
        return ResponseFactory::create($result);
    }

    public static function getApiDocInfo($service)
    {
        return ['paths' => [], 'definitions' => []];
    }
}
