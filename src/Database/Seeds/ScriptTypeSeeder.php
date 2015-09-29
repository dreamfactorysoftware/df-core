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
            'name'        => 'php',
            'class_name'  => Php::class,
            'label'       => 'PHP',
            'description' => 'Script handler using native PHP.',
            'sandboxed'   => false
        ],
        [
            'name'        => 'nodejs',
            'class_name'  => NodeJs::class,
            'label'       => 'Node.js',
            'description' => 'Server-side JavaScript handler using the Node.js engine.',
            'sandboxed'   => false
        ],
        [
            'name'        => 'v8js',
            'class_name'  => V8Js::class,
            'label'       => 'V8js',
            'description' => 'Server-side JavaScript handler using the V8js engine.',
            'sandboxed'   => true
        ]
    ];
}
