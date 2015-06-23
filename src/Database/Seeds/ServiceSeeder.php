<?php
namespace DreamFactory\Core\Database\Seeds;

class ServiceSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\Service';

    protected $records = [
        [
            'name'        => 'system',
            'label'       => 'System Management',
            'description' => 'Service for managing system resources.',
            'is_active'   => 1,
            'type'        => 'system',
            'mutable'     => 0,
            'deletable'   => 0
        ],
        [
            'name'        => 'api_docs',
            'label'       => 'Live API Docs',
            'description' => 'API documenting and testing service.',
            'is_active'   => 1,
            'type'        => 'swagger',
            'mutable'     => 0,
            'deletable'   => 0
        ],
        [
            'name'        => 'event',
            'label'       => 'Events',
            'description' => 'Service for displaying and subscribing to broadcast system events.',
            'is_active'   => 1,
            'type'        => 'event',
            'mutable'     => 0,
            'deletable'   => 0
        ]
    ];
}
