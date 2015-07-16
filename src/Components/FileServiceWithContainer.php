<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Aws\Models\AwsConfig;
use DreamFactory\Core\Models\FilePublicPath;

trait FileServiceWithContainer
{
    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $awsConfig = new AwsConfig();
        $pathConfig = new FilePublicPath();
        $out = null;

        $awsSchema = $awsConfig->getConfigSchema();
        $pathSchema = $pathConfig->getConfigSchema();

        foreach ($pathSchema as $k => $schema) {
            if ($schema['name'] === 'container') {
                $pathSchema[$k]['type'] = 'text';
                unset($pathSchema[$k]['values']);
                unset($pathSchema[$k]['description']);
            }
        }

        if (!empty($awsSchema)) {
            $out = $awsSchema;
        }
        if (!empty($pathSchema)) {
            $out = ($out) ? array_merge($out, $pathSchema) : $pathSchema;
        }

        return $out;
    }
}