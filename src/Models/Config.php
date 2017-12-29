<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use ServiceManager;

class Config extends BaseServiceConfigModel
{
    use SingleRecordModel;

    protected $table = 'system_config';

    protected $fillable = [
        'service_id',
        'invite_email_service_id',
        'invite_email_template_id',
        'password_email_service_id',
        'password_email_template_id',
        'default_app_id'
    ];

    protected $casts = [
        'service_id'                 => 'integer',
        'invite_email_service_id'    => 'integer',
        'invite_email_template_id'   => 'integer',
        'password_email_service_id'  => 'integer',
        'password_email_template_id' => 'integer',
        'default_app_id'             => 'integer',
    ];

    protected $hidden = ['created_by_id', 'last_modified_by_id'];

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'default_app_id':
                $schema['label'] = 'Default Application';
                $apps = App::get();
                $appsList = [
                    [
                        'label' => '',
                        'name'  => null
                    ]
                ];
                foreach ($apps as $app) {
                    $appsList[] = [
                        'label' => $app->name,
                        'name'  => $app->id
                    ];
                }
                $schema['type'] = 'picklist';
                $schema['values'] = $appsList;
                $schema['description'] = 'Select a default application to be used for the system UI.';
                break;
            case 'invite_email_service_id':
            case 'password_email_service_id':
                $label = substr($schema['label'], 0, strlen($schema['label']) - 11);
                $services = ServiceManager::getServiceListByGroup(ServiceTypeGroups::EMAIL, ['id', 'label'], true);
                $emailSvcList = [
                    [
                        'label' => '',
                        'name'  => null
                    ]
                ];
                foreach ($services as $service) {
                    $emailSvcList[] = ['label' => array_get($service, 'label'), 'name' => array_get($service, 'id')];
                }
                $schema['type'] = 'picklist';
                $schema['values'] = $emailSvcList;
                $schema['label'] = $label . ' Service';
                $schema['description'] =
                    'Select an Email service for sending out ' .
                    $label .
                    '.';
                break;
            case 'invite_email_template_id':
            case 'password_email_template_id':
                $label = substr($schema['label'], 0, strlen($schema['label']) - 11);
                $templates = EmailTemplate::get();
                $templateList = [
                    [
                        'label' => '',
                        'name'  => null
                    ]
                ];
                foreach ($templates as $template) {
                    $templateList[] = [
                        'label' => $template->name,
                        'name'  => $template->id
                    ];
                }
                $schema['type'] = 'picklist';
                $schema['values'] = $templateList;
                $schema['label'] = $label . ' Template';
                $schema['description'] = 'Select an Email template to use for ' .
                    $label .
                    '.';
                break;
        }
    }
}