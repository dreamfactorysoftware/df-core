<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\ServiceUnavailableException;

/**
 * ScriptConfig
 *
 * @property integer $service_id
 * @property string  $type
 * @property string  $content
 * @property string  $config
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereType($value)
 */
class ScriptConfig extends BaseServiceConfigModel
{
    protected $table = 'script_config';

    protected $fillable = ['service_id', 'type', 'content', 'config'];

    protected $appends = ['engine'];

    protected $casts = ['service_id' => 'integer', 'config' => 'array'];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $engine = [];

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        if (!empty($disable = config('df.scripting.disable')))
        {
            switch (strtolower($disable)){
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    $type = (isset($config['type'])) ? $config['type'] : null;
                    if (!empty($type) && (false !== stripos($disable, $type))){
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        return true;
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

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'type':
                $schema['label'] = 'Scripting Engine Type';
                $schema['description'] =
                    'The Scripting Engine able to run this script.';
                $values = ScriptType::all(['name', 'label', 'sandboxed'])->toArray();
                $schema['type'] = 'picklist';
                $schema['values'] = $values;
                $schema['default'] = 'v8js';
                break;
            case 'content':
                $schema['label'] = 'Content';
                $schema['type'] = 'text';
                $schema['description'] =
                    'The content of the script written in the appropriate language.';
                break;
            case 'config':
                $schema['label'] = 'Additional Configuration';
                $schema['type'] = 'object';
                $schema['object'] =
                    [
                        'key'   => ['label' => 'Name', 'type' => 'string'],
                        'value' => ['label' => 'Value', 'type' => 'string']
                    ];
                $schema['description'] =
                    'An array of additional configuration needed for the script to run.';
                break;
        }
    }

}
