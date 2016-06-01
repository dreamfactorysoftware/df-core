<?php

namespace DreamFactory\Core\Resources\System;

class EmailTemplate extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = \DreamFactory\Core\Models\EmailTemplate::class;

    public static function getApiDocInfo($service, array $resource = [])
    {
        $base = parent::getApiDocInfo($service, $resource);

        $commonProperties = [
            'id'          => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Identifier of this template.',
            ],
            'name'        => [
                'type'        => 'string',
                'description' => 'Displayable name of this template.',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'Description of this template.',
            ],
            'to'          => [
                'type'        => 'array',
                'description' => 'Single or multiple receiver addresses.',
                'items'       => [
                    '$ref' => '#/definitions/EmailAddress',
                ],
            ],
            'cc'          => [
                'type'        => 'array',
                'description' => 'Optional CC receiver addresses.',
                'items'       => [
                    '$ref' => '#/definitions/EmailAddress',
                ],
            ],
            'bcc'         => [
                'type'        => 'array',
                'description' => 'Optional BCC receiver addresses.',
                'items'       => [
                    '$ref' => '#/definitions/EmailAddress',
                ],
            ],
            'subject'     => [
                'type'        => 'string',
                'description' => 'Text only subject line.',
            ],
            'body_text'   => [
                'type'        => 'string',
                'description' => 'Text only version of the body.',
            ],
            'body_html'   => [
                'type'        => 'string',
                'description' => 'Escaped HTML version of the body.',
            ],
            'from'        => [
                '$ref' => '#/definitions/EmailAddress',
            ],
            'reply_to'    => [
                '$ref' => '#/definitions/EmailAddress',
            ],
            'defaults'    => [
                'type'        => 'array',
                'description' => 'Array of default name value pairs for template replacement.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
        ];

        $stampProperties = [
            'created_date'       => [
                'type'        => 'string',
                'description' => 'Date this record was created.',
                'readOnly'    => true,
            ],
            'last_modified_date' => [
                'type'        => 'string',
                'description' => 'Date this record was last modified.',
                'readOnly'    => true,
            ],
        ];

        $models = [
            'EmailTemplateRequest'  => [
                'type'       => 'object',
                'properties' => $commonProperties,
            ],
            'EmailTemplateResponse' => [
                'type'       => 'object',
                'properties' => array_merge(
                    $commonProperties,
                    $stampProperties
                ),
            ],
            'EmailAddress'          => [
                'type'       => 'object',
                'properties' => [
                    'name'  => [
                        'type'        => 'string',
                        'description' => 'Optional name displayed along with the email address.',
                    ],
                    'email' => [
                        'type'        => 'string',
                        'description' => 'Required email address.',
                    ],
                ],
            ],
        ];

        $base['definitions'] = array_merge($base['definitions'], $models);

        return $base;
    }
}