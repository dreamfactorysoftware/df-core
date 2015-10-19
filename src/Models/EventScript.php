<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\ServiceUnavailableException;

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
 * @method static \Illuminate\Database\Query\Builder|EventScript whereIsActive($value)
 * @method static \Illuminate\Database\Query\Builder|EventScript whereType($value)
 */
class EventScript extends BaseModel
{
    /**
     * @const string The private cache file
     */
    const CACHE_PREFIX = 'script.';
    /**
     * @const integer How long a EventScript cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'last_modified_date';

    protected $table = 'event_script';

    protected $primaryKey = 'name';

    protected $fillable = ['name', 'type', 'content', 'config', 'is_active', 'affects_process'];

    protected $casts = ['is_active' => 'boolean', 'affects_process' => 'boolean'];

    public $incrementing = false;

    public function validate(array $data = [], $throwException = true)
    {
        if (empty($data)) {
            $data = $this->attributes;
        }

        if (!empty($disable = config('df.scripting.disable')))
        {
            switch (strtolower($disable)){
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    $type = (isset($data['type'])) ? $data['type'] : null;
                    if (!empty($type) && (false !== stripos($disable, $type))){
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        return parent::validate($data, $throwException);
    }

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
}