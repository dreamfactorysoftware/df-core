<?php
namespace DreamFactory\Core\Database\Seeds;

class ScriptTypeSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\ScriptType';

    protected $records = [
        [
            'name'        => 'php',
            'class_name'  => 'DreamFactory\\Core\\Scripting\\Engines\\Php',
            'label'       => 'PHP',
            'description' => 'Script handler using native PHP.',
            'sandboxed'   => 0
        ],
        [
            'name'        => 'v8js',
            'class_name'  => 'DreamFactory\\Core\\Scripting\\Engines\\V8js',
            'label'       => 'V8Js',
            'description' => 'Server-side JavaScript handler using the V8Js engine.',
            'sandboxed'   => 1
        ]
    ];
}
