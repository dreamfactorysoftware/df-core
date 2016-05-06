<?php
namespace DreamFactory\Core\Services\Script;

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
}
