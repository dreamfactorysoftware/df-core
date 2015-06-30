<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Library\Utility\Enums\Verbs;

class Config extends BaseSystemResource
{

    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

        parent::__construct($settings);

        $this->model = \DreamFactory\Core\Models\Config::class;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $_config = [];

        $_config['apis'] = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'     => 'GET',
                        'summary'    => 'getConfig() - Retrieve system configuration properties.',
                        'nickname'   => 'getConfig',
                        'type'       => 'ConfigResponse',
                        'event_name' => $eventPath . '.read',
                        'notes'      => 'The retrieved properties control how the system behaves.',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'setConfig() - Update one or more system configuration properties.',
                        'nickname'         => 'setConfig',
                        'type'             => 'ConfigResponse',
                        'event_name'       => $eventPath . '.update',
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of properties to set.',
                                'allowMultiple' => false,
                                'type'          => 'ConfigRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'Post data should be an array of properties.',
                    ],
                ],
                'description' => 'Operations for system configuration options.',
            ],
        ];

        $_commonProperties = [
            'open_reg_role_id'           => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Default Role Id assigned to newly registered users, set to null to turn off open registration.',
            ],
            'open_reg_email_service_id'  => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Set to an email-type service id to require email confirmation of newly registered users.',
            ],
            'open_reg_email_template_id' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Default email template used for open registration email confirmations.',
            ],
            'invite_email_service_id'    => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Set to an email-type service id to allow user invites and invite confirmations via email service.',
            ],
            'invite_email_template_id'   => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Default email template used for user invitations and confirmations via email service.',
            ],
            'password_email_service_id'  => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Set to an email-type service id to require email confirmation to reset passwords, otherwise defaults to security question and answer.',
            ],
            'password_email_template_id' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Default email template used for password reset email confirmations.',
            ],
            'guest_role_id'              => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Role Id assigned for all guest sessions, set to null to require authenticated sessions.',
            ],
            'editable_profile_fields'    => [
                'type'        => 'string',
                'description' => 'Comma-delimited list of fields the user is allowed to edit.',
            ],
            'allowed_hosts'              => [
                'type'        => 'array',
                'description' => 'CORS whitelist of allowed remote hosts.',
                'items'       => [
                    '$ref' => 'HostInfo',
                ],
            ],
            'restricted_verbs'           => [
                'type'        => 'array',
                'description' => 'An array of HTTP verbs that must be tunnelled on this server.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
            'install_type'               => [
                'type'        => 'integer',
                'description' => 'The internal installation type ID for this server.',
            ],
            'install_name'               => [
                'type'        => 'string',
                'description' => 'The name of the installation type for this server.',
            ],
            'is_hosted'                  => [
                'type'        => 'boolean',
                'description' => 'True if this is a free hosted DreamFactory DSP.',
            ],
            'is_private'                 => [
                'type'        => 'boolean',
                'description' => 'True if this is a non-free DreamFactory hosted DSP.',
            ],
            'is_guest'                   => [
                'type'        => 'boolean',
                'description' => 'True if the current user has not logged in.',
            ],
            'paths'                      => [
                'type'        => 'array',
                'description' => 'An array of the various absolute paths of this server.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
            'states'                     => [
                'type'        => 'StateInfo',
                'description' => 'An array of the current platform state from various perspectives.',
            ],
            'timestamp_format'           => [
                'type'        => 'string',
                'description' => 'The date/time format used for timestamps.',
            ],
        ];

        @ksort($_commonProperties);

        $_config['models'] = [
            'ConfigRequest'  => [
                'id'         => 'ConfigRequest',
                'properties' => $_commonProperties,
            ],
            'ConfigResponse' => [
                'id'         => 'ConfigResponse',
                'properties' => array_merge(
                    $_commonProperties,
                    [
                        'dsp_version' => [
                            'type'        => 'string',
                            'description' => 'Version of the DSP software.',
                        ],
                        'db_version'  => [
                            'type'        => 'string',
                            'description' => 'Version of the database schema.',
                        ],
                    ]
                ),
            ],
            'HostInfo'       => [
                'id'         => 'HostInfo',
                'properties' => [
                    'host'       => [
                        'type'        => 'string',
                        'description' => 'URL, server name, or * to define the CORS host.',
                    ],
                    'is_enabled' => [
                        'type'        => 'boolean',
                        'description' => 'Allow this host\'s configuration to be used by CORS.',
                    ],
                    'verbs'      => [
                        'type'        => 'array',
                        'description' => 'Allowed HTTP verbs for this host.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'StateInfo'      => [
                'id'          => 'StateInfo',
                'description' => 'An array of platform states from various perspectives.',
                'required'    => true,
                'properties'  => [
                    'operation_state' => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'required'    => true,
                        'description' => <<<HTML
<div>
    <h3>The current enterprise/hosted platform state. Valid states are:</h3>
    <table>
        <thead><tr><th>Value</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td>-1</td><td>Unpublished, non-hosted, private or unknown</td></tr>
            <tr><td>0</td><td>Ready but <strong>not activated</strong></td></tr>
            <tr><td>1</td><td>Ready and <strong>activated</strong></td></tr>
            <tr><td>2</td><td>Locked by provisioning manager</td></tr>
            <tr><td>3</td><td>Maintenance Mode</td></tr>
            <tr><td>4</td><td>Banned and not <strong>available</strong></td></tr>
        </tbody>
    </table>
</div>
HTML
                    ],
                    'provision_state' => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'required'    => true,
                        'description' => <<<HTML
<div>
    <h3>The current state of platform provisioning. Valid states are:</h3>
    <table>
        <thead><tr><th>Value</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td>0</td><td>Request queued</td></tr>
            <tr><td>1</td><td>Provisioning in progress</td></tr>
            <tr><td>2</td><td>Provisioning complete</td></tr>
            <tr><td>3</td><td>Deprovisioning in progress</td></tr>
            <tr><td>4</td><td>Deprovisioning complete</td></tr>
            <tr><td>10</td><td>Error queuing request</td></tr>
            <tr><td>12</td><td>Provisioning Error</td></tr>
            <tr><td>14</td><td>Deprovisioning Error</td></tr>
        </tbody>
    </table>
</div>
HTML
                    ],
                    'ready_state'     => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'required'    => true,
                        'description' => <<<HTML
<div>
    <h3>The current ready state of the DSP. Valid states are:</h3>
    <table>
        <thead><tr><th>Value</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td>0</td><td>Platform Administrator Missing</td></tr>
            <tr><td>1</td><td>Default Platform Data Missing</td></tr>
            <tr><td>2</td><td>Install/Migrate Platform Database Schema</td></tr>
            <tr><td>3</td><td>Platform Ready</td></tr>
            <tr><td>4</td><td>Default Platform Schema Missing</td></tr>
            <tr><td>5</td><td>Platform Upgrade Required</td></tr>
            <tr><td>6</td><td>Welcome Page Required</td></tr>
            <tr><td>7</td><td>Platform Database Ready</td></tr>
        </tbody>
    </table>
</div>
HTML
                    ],
                ],
            ],
        ];

        @ksort($_config['models']);
        @ksort($_config['models']['ConfigResponse']['properties']);

        return $_config;
    }

    protected function handlePOST()
    {
        if (!empty($this->resource)) {
            throw new BadRequestException('Create record by identifier not currently supported.');
        }

        $records = $this->getPayloadData(self::RECORD_WRAPPER);
        unset($records[0]['api_key']);

        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $this->triggerActionEvent($this->response);

        $modelClass = $this->model;
        $result = $modelClass::bulkCreate($records, $this->request->getParameters());

        $response = ResponseFactory::create($result, $this->nativeFormat, ServiceResponseInterface::HTTP_CREATED);

        return $response;
    }
}