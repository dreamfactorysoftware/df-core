<?php

namespace DreamFactory\Core\Models;

/**
 * EventScript
 *
 * @property string     $name
 * @property string     $type
 * @property string     $content
 * @property string     $config
 * @property boolean    $is_active
 * @property boolean    $affects_process
 * @method static \Illuminate\Database\Query\Builder|EventScript whereName($value)
 * @method static \Illuminate\Database\Query\Builder|EventScript whereType($value)
 */
class EventScript extends BaseSystemModel
{
    /**
     * @const string The private cache file
     */
    const CACHE_PREFIX = 'script.';
    /**
     * @const integer How long a EventScript cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    protected $table = 'event_script';

    protected $fillable = ['name', 'type', 'content', 'config', 'is_active', 'affects_process'];

    protected $casts = ['is_active' => 'boolean', 'affects_process' => 'boolean'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function scriptType()
    {
        return $this->belongsTo('DreamFactory\Core\Models\ScriptType', 'type', 'name');
    }

    /**
     * Determine the handler for the script type
     *
     * @return string|null
     */
    protected function getScriptHandler()
    {
        if (null !== $typeInfo = $this->scriptType()->first()) {
            // lookup related script type model
            return $typeInfo->class_name;
        }

        return null;
    }
}