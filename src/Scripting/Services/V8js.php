<?php
namespace DreamFactory\Core\Scripting\Services;

/**
 * V8js Script
 * V8js scripting as a Service
 */
class V8js extends Script
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
        $settings['config']['type'] = 'v8js';
        parent::__construct($settings);
    }
}
