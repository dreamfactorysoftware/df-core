<?php
namespace DreamFactory\Core\Services\Script;

/**
 * Nodejs Script
 * Nodejs scripting as a Service
 */
class Nodejs extends Script
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
        $settings['config']['type'] = 'nodejs';
        parent::__construct($settings);
    }
}
