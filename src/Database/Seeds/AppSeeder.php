<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Core\Models\App;

class AppSeeder extends BaseModelSeeder
{
    protected $modelClass = App::class;

    protected $records = [
        [
            'name'        => 'admin',
            'api_key'     => '6498a8ad1beb9d84d63035c5d1120c007fad6de706734db9689f8996707e0f7d',
            'description' => 'Default Admin Application',
            'is_active'   => 1,
            'type'        => 3,
            'path'        => 'dreamfactory/app/index.html'
        ],
    ];
}
