<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\BadRequestException;

/**
 * Class CorsConfig
 *
 * @package DreamFactory\Core\Models
 */
class CorsConfig extends BaseSystemModel
{
    /**
     * @var string
     */
    protected $table = 'cors_config';

    /**
     * @var array
     */
    protected $fillable = ['path', 'origin', 'header', 'method', 'max_age', 'enabled'];

    /**
     * @var array
     */
    protected $casts = [
        'id'      => 'integer',
        'method'  => 'integer',
        'max_age' => 'integer',
        'enabled' => 'boolean'
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        static::creating(
            function (CorsConfig $config){
                $config->validateAndClean();

                return true;
            }
        );

        static::updating(
            function (CorsConfig $config){
                $config->validateAndClean();

                return true;
            }
        );
    }

    /**
     * Validates and cleans model attributes
     *
     * @throws BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function validateAndClean()
    {
        $path = $this->getAttribute('path');
        $header = $this->getAttribute('header');
        $method = $this->getAttribute('method');

        if (empty($path)) {
            throw new BadRequestException('No path specified. Use * to apply to all api paths.');
        }

        if (empty($header)) {
            $this->setAttribute('header', '*');
        }

        if (is_string($method)) {
            $method = explode(',', $method);
        }

        if (is_array($method)) {
            $action = 0;
            foreach ($method as $verb) {
                $action = $action | VerbsMask::toNumeric($verb);
            }
            $method = $action;
        }
        $this->setAttribute('method', $method);
    }

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
        } else if (is_string($method)) {
            $method = (integer)$method;
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