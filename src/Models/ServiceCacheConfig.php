<?php

namespace DreamFactory\Core\Models;

/**
 * ServiceCacheConfig
 *
 * @property integer $service_id
 * @property boolean $cache_enabled
 * @property string  $cache_ttl
 * @method static \Illuminate\Database\Query\Builder|Service whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereCacheEnabled($value)
 */
class ServiceCacheConfig extends BaseServiceConfigModel
{
    protected $table = 'service_cache_config';

    protected $fillable = [
        'service_id',
        'cache_enabled',
        'cache_ttl'
    ];

    protected $casts = ['cache_enabled' => 'boolean', 'cache_ttl' => 'integer', 'service_id' => 'integer'];

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'cache_enabled':
                $schema['label'] = 'Data Retrieval Caching Enabled';
                $schema['description'] =
                    'Enable caching of GET requests particularly for this service.' .
                    ' Only GET requests without payload are cached.';
                break;
            case 'cache_ttl':
                $schema['label'] = 'Cache Time To Live (seconds)';
                $schema['description'] =
                    'The amount of time each cached response is allowed to last.' .
                    ' Once expired, a new request to the service is made.';
                break;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}