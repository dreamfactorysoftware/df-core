<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;

/**
 * Service
 *
 * @property integer $id
 * @property string  $name
 * @property string  $label
 * @property string  $description
 * @property boolean $is_active
 * @property boolean $mutable
 * @property boolean $deletable
 * @property string  $type
 * @property array   $config
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Service whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereLabel($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereIsActive($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereType($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereNativeFormatId($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Service whereLastModifiedDate($value)
 */
class Service extends BaseSystemModel
{
    protected $table = 'service';

    protected $fillable = ['name', 'label', 'description', 'is_active', 'type', 'config'];

    protected $guarded = [
        'id',
        'mutable',
        'deletable',
        'created_date',
        'last_modified_date',
        'created_by_id',
        'last_modified_by_id'
    ];

    protected $appends = ['config'];

    protected $casts = ['is_active' => 'boolean', 'mutable' => 'boolean', 'deletable' => 'boolean', 'id' => 'integer'];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $config = [];

    public function disableRelated()
    {
        // allow config
    }

    public static function boot()
    {
        parent::boot();

        static::created(
            function (Service $service){
                if (!empty($service->config)) {
                    // take the type information and get the config_handler class
                    // set the config giving the service id and new config
                    $serviceCfg = $service->getConfigHandler();
                    if (!empty($serviceCfg)) {
                        return $serviceCfg::setConfig($service->getKey(), $service->config);
                    }
                }

                return true;
            }
        );

        static::deleted(
            function (Service $service){
                // take the type information and get the config_handler class
                // set the config giving the service id and new config
                $serviceCfg = $service->getConfigHandler();
                if (!empty($serviceCfg)) {
                    return $serviceCfg::removeConfig($service->getKey());
                }

                return true;
            }
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'type', 'name');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function serviceDocs()
    {
        return $this->hasMany(ServiceDoc::class, 'service_id', 'id');
    }

    /**
     * @param $name
     *
     * @return null
     */
    public static function getTypeByName($name)
    {
        $typeRec = static::whereName($name)->get(['type'])->first();

        return (isset($typeRec, $typeRec['type'])) ? $typeRec['type'] : null;
    }

    /**
     * @return array
     */
    public static function available()
    {
        // need to cache this possibly
        return static::lists('name');
    }

    /**
     * Determine the handler for the extra config settings
     *
     * @return ServiceConfigHandlerInterface|null
     */
    protected function getConfigHandler()
    {
        if (null !== $typeInfo = $this->serviceType()->first()) {
            // lookup related service type config model
            return $typeInfo->config_handler;
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getConfigAttribute()
    {
        // take the type information and get the config_handler class
        // set the config giving the service id and new config
        $serviceCfg = $this->getConfigHandler();
        if (!empty($serviceCfg)) {
            return $serviceCfg::getConfig($this->getKey());
        }

        return $this->config;
    }

    /**
     * @param array $val
     */
    public function setConfigAttribute(Array $val)
    {
        $this->config = $val;
        // take the type information and get the config_handler class
        // set the config giving the service id and new config
        $serviceCfg = $this->getConfigHandler();
        if (!empty($serviceCfg)) {
            if ($this->exists) {
                if ($serviceCfg::validateConfig($this->config, false)) {
                    $serviceCfg::setConfig($this->getKey(), $this->config);
                }
            } else {
                $serviceCfg::validateConfig($this->config);
            }
        }
    }
}