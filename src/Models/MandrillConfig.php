<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class MandrillConfig extends CloudEmailConfig
{
    protected $fillable = [
        'service_id',
        'key',
        'parameters'
    ];

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        $validator = static::makeValidator($config, [
            'key' => 'required'
        ], $create);

        if ($validator->fails()) {
            $messages = $validator->messages()->getMessages();
            throw new BadRequestException('Validation failed.', null, null, $messages);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $out = [];
        foreach ($schema as $key => $field) {
            if ($field['name'] === 'key') {
                $out[] = $schema[$key];
            }
        }

        return $out;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'key':
                $schema['label'] = 'API Key';
                $schema['description'] = 'A Mandrill service API Key.';
                break;
        }
    }
}