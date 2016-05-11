<?php

namespace DreamFactory\Core\Scripting\Models;

use DreamFactory\Core\Contracts\ScriptEngineTypeInterface;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use ScriptEngineManager;

/**
 * ScriptConfig
 *
 * @property integer $service_id
 * @property string  $content
 * @property string  $config
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereType($value)
 */
class ScriptConfig extends BaseServiceConfigModel
{
    protected $table = 'script_config';

    protected $fillable = ['service_id', 'content', 'config'];

    protected $appends = ['engine'];

    protected $casts = ['service_id' => 'integer', 'config' => 'array'];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $engine = [];

    public static function getType()
    {
        return '';
    }
    
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
                    $type = static::getType();
                    if (!empty($type) && (false !== stripos($disable, $type))){
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        return true;
    }
    
    /**
     * @return mixed
     */
    public function getEngineAttribute()
    {
        /** @type ScriptEngineTypeInterface $typeInfo */
        if (null !== $typeInfo = ScriptEngineManager::getScriptEngineType($this->getType())) {
            $this->engine = $typeInfo->toArray();
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
