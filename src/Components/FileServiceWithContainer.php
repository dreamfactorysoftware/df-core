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
                unset($pathSchema[$k]['description']);
            }
        }
    }
}