<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class SmtpConfig extends BaseEmailServiceConfigModel
{
    protected $table = 'smtp_config';

    protected $fillable = [
        'service_id',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'parameters'
    ];

    protected $encrypted = ['username', 'password'];

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        $validator = static::makeValidator($config, [
            'host'     => 'required',
            'username' => 'required',
            'password' => 'required'
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
            case 'host':
                $schema['description'] = 'SMTP Host.';
                break;
            case 'port':
                $schema['description'] = 'SMTP Port (default: 587).';
                break;
            case 'encryption':
                $schema['description'] = 'SMTP Encryption: tls/ssl.';
                break;
            case 'username':
                $schema['description'] = 'SMTP Username.';
                break;
            case 'password':
                $schema['description'] = 'SMTP Password.';
                break;
        }
    }
}