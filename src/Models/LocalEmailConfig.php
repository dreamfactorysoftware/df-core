<?php

namespace DreamFactory\Core\Models;

class LocalEmailConfig extends CloudEmailConfig
{
    protected $fillable = [
        'service_id',
        'parameters'
    ];

    protected $encrypted = [];

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $out = [];
        foreach ($schema as $key => $field) {
            if ($field['name'] === 'parameters') {
                $out[] = $schema[$key];
            }
        }

        return $out;
    }
}