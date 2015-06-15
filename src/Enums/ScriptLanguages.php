<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * ScriptLanguages
 * Supported and future DSP scripting languages
 */
class ScriptLanguages extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    const __default = self::V8JS;

    /**
     * @var string
     */
    const V8JS = 'v8js';
    /**
     * @var string
     */
    const NODEJS = 'nodejs';
    /**
     * @var string
     */
    const LUA = 'lua';
    /**
     * @var string
     */
    const PYTHON = 'py';
    /**
     * @var string
     */
    const PHP = 'php';
    /**
     * @var string
     */
    const RUBY = 'rb';
}