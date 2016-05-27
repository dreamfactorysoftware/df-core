<?php
namespace DreamFactory\Core\Scripting\Services;

/**
 * PHP Script
 * PHP scripting as a Service
 */
class Php extends Script
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
        $settings['config']['type'] = 'php';
        parent::__construct($settings);
    }
}
