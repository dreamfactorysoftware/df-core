<?php

class FileServiceLocalTest extends \DreamFactory\Core\Testing\FileServiceTestCase
{
    protected static $staged = false;

    protected $serviceId = 'files';

    public function stage()
    {
        parent::stage();

        if (!$this->serviceExists('files')) {
            \DreamFactory\Core\Models\Service::create(
                [
                    'name'        => 'files',
                    'label'       => 'Local file service',
                    'description' => 'Local file service for unit test',
                    'is_active'   => true,
                    'type'        => 'local_file',
                    'config' => [ 'container' => 'local']
                ]
            );
        }
    }
}