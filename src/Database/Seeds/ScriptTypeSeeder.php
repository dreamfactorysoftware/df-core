<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Core\Models\ScriptType;
use DreamFactory\Core\Scripting\Engines\NodeJs;
use DreamFactory\Core\Scripting\Engines\Php;
use DreamFactory\Core\Scripting\Engines\V8Js;

class ScriptTypeSeeder extends BaseModelSeeder
{
    protected $modelClass = ScriptType::class;

    protected $records = [
        [
            'name'        => 'nodejs',
            'class_name'  => NodeJs::class,
            'label'       => 'NodeJs',
            'description' => 'Server-side JavaScript handler using the NodeJs engine.',
            'sandboxed'   => false
        ],
        [
            'name'        => 'php',
            'class_name'  => Php::class,
            'label'       => 'PHP',
            'description' => 'Script handler using native PHP.',
            'sandboxed'   => false
        ],
        [
            'name'        => 'v8js',
            'class_name'  => V8Js::class,
            'label'       => 'V8Js',
            'description' => 'Server-side JavaScript handler using the V8Js engine.',
            'sandboxed'   => true
        ]
    ];
}
