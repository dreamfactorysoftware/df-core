<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class MailGunConfig extends CloudEmailConfig
{
    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        $validator = static::makeValidator($config, [
            'domain' => 'required',
            'key'    => 'required'
        ], $create);

        if ($validator->fails()) {
            $messages = $validator->messages()->getMessages();
            throw new BadRequestException('Validation failed.', null, null, $messages);
        }

        return true;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'domain':
                $schema['label'] = 'Mailgun Domain';
                $schema['description'] = 'Your Mailgun domain name.';
                break;
            case 'key':
                $schema['label'] = 'API Key';
                $schema['description'] = 'Mailgun service API Key.';
                break;
        }
    }
}