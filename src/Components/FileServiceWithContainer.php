<?php

namespace DreamFactory\Core\Components;

trait FileServiceWithContainer
{
    protected static function updatePathSchema(&$pathSchema)
    {
        foreach ($pathSchema as $k => $schema) {
            if ($schema['name'] === 'container') {
                $pathSchema[$k]['type'] = 'text';
                unset($pathSchema[$k]['values']);
                $pathSchema[$k]['label'] = 'Container';
                $pathSchema[$k]['description'] =
                    'Enter a Container (root directory) for your storage service. It will be created if does not exit already.';
            }
        }
    }
}