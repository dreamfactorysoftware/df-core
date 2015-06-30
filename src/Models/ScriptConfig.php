<?php

namespace DreamFactory\Core\Models;

/**
 * ScriptConfig
 *
 * @property integer    $service_id
 * @property string     $type
 * @property string     $content
 * @property string     $config
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereType($value)
 */
class ScriptConfig extends BaseServiceConfigModel
{
    /**
     * @const string The private cache file
     */
    const CACHE_PREFIX = 'script.';
    /**
     * @const integer How long a ScriptConfig cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    protected $table = 'script_config';

    protected $fillable = ['service_id', 'type', 'engine', 'content', 'config'];

    protected $appends = ['engine'];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $engine = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function scriptType()
    {
        return $this->belongsTo(ScriptType::class, 'type', 'name');
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

    /**
     * @return mixed
     */
    public function getEngineAttribute()
    {
        $engine = $this->scriptType()->first();
        if (!empty($engine)) {
            $this->engine = $engine->toArray();
        }

        return $this->engine;
    }
}