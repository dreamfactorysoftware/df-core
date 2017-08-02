<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\VerbsMask;
use Illuminate\Database\Query\Builder;

/**
 * Class CorsConfig
 *
 * @property integer $id
 * @property string  $description
 * @property string  $path
 * @property string  $origin
 * @property string  $header
 * @property string  $exposed_header
 * @property integer $max_age
 * @property integer $method
 * @property boolean $supports_credentials
 * @property boolean $enabled
 * @method static Builder|CorsConfig whereId($value)
 * @method static Builder|CorsConfig whereName($value)
 * @method static Builder|CorsConfig whereEnabled($value)
 * @package DreamFactory\Core\Models
 */
class CorsConfig extends BaseSystemModel
{
    /** Cache Key Constant */
    const CACHE_KEY = 'df-cors-config';

    /**
     * @var string
     */
    protected $table = 'cors_config';

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $casts = [
        'id'                   => 'integer',
        'method'               => 'integer',
        'max_age'              => 'integer',
        'enabled'              => 'boolean',
        'supports_credentials' => 'boolean',
    ];

    protected $rules = [
        'path'   => 'required',
        'origin' => 'required'
    ];

    protected $validationMessages = [
        'required' => 'The :attribute is required.'
    ];

    /**
     * Converts verb masks to array of verbs (string) as needed.
     *
     * @param $method
     *
     * @return string
     */
    public function getMethodAttribute($method)
    {
        if (is_array($method)) {
            return $method;
        } else {
            if (is_string($method)) {
                $method = (integer)$method;
            }
        }

        return VerbsMask::maskToArray($method);
    }

    /**
     * Converts methods array to verb masks
     *
     * @param $method
     *
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function setMethodAttribute($method)
    {
        if (is_array($method)) {
            $action = 0;
            foreach ($method as $verb) {
                $action = $action | VerbsMask::toNumeric($verb);
            }
        } else {
            $action = $method;
        }

        $this->attributes['method'] = $action;
    }
}