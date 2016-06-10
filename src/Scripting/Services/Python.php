<?php
namespace DreamFactory\Core\Scripting\Services;

use DreamFactory\Core\Contracts\ServiceResponseInterface;

/**
 * Python Script
 * Python scripting as a Service
 */
class Python extends Script
{
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
        $settings['config']['type'] = 'python';
        parent::__construct($settings);
    }

    /** {@inheritdoc} */
    protected function getRequestData()
    {
        $data = [
            'request'  => $this->request->toArray(),
            'response' => [
                'content'      => null,
                'content_type' => null,
                'status_code'  => ServiceResponseInterface::HTTP_OK
            ],
            'resource' => $this->resourcePath
        ];

        $parameters = array_get($data, 'request.parameters');

        // If a json payload is provided in a GET call then that
        // payload gets passed in as query parameters (key) and it
        // later causes Python engine to break. The following
        // code unsets any invalid json key in parameters.
        if (!empty($parameters)) {
            if (is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    if ($this->isJson($key)) {
                        unset($parameters[$key]);
                    }
                }
            }
        }

        array_set($data, 'request.parameters', $parameters);

        return $data;
    }

    /**
     * Checks to see if a string is valid json or not.
     *
     * @param $string
     *
     * @return bool
     */
    protected function isJson($string)
    {
        $output = json_decode($string, true);
        if ($output === null) {
            return false;
        }

        return true;
    }
}
