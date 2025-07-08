<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\DsnToConnectionConfig;
use DreamFactory\Core\Components\UpdatesSender;
use DreamFactory\Core\Components\ServiceHealthChecker;
use DreamFactory\Core\Events\ServiceDeletedEvent;
use DreamFactory\Core\Events\ServiceModifiedEvent;
use DreamFactory\Core\Exceptions\BadRequestException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use ServiceManager;

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
 * @method static Builder|Service whereId($value)
 * @method static Builder|Service whereName($value)
 * @method static Builder|Service whereLabel($value)
 * @method static Builder|Service whereIsActive($value)
 * @method static Builder|Service whereMutable($value)
 * @method static Builder|Service whereDeletable($value)
 * @method static Builder|Service whereType($value)
 * @method static Builder|Service whereCreatedDate($value)
 * @method static Builder|Service whereLastModifiedDate($value)
 */
class Service extends BaseSystemModel
{
    use DsnToConnectionConfig;

    protected $table = 'service';

    protected $fillable = ['name', 'label', 'description', 'is_active', 'type', 'config'];

    protected $rules = [
        'name' => 'regex:/(^[A-Za-z0-9_\-]+$)+/'
    ];

    protected $validationMessages = [
        'regex' => 'Service name should only contain letters, numbers, underscores and dashes.'
    ];

    protected $guarded = [
        'id',
        'mutable',
        'deletable',
        'created_date',
        'last_modified_date',
        'created_by_id',
        'last_modified_by_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mutable'   => 'boolean',
        'deletable' => 'boolean',
        'id'        => 'integer',
        'config'    => 'array',
    ];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $config;

    public function disableRelated()
    {
        // allow config
    }

    public static function boot()
    {
        parent::boot();

        static::created(
            function (Service $service) {
                if (!empty($service->config)) {
                    $serviceCfg = $service->getConfigHandler();
                    if (!empty($serviceCfg) && $serviceCfg::handlesStorage()) {
                        $serviceCfg::storeConfig($service->getKey(), $service->config);
                    }
                }
                $healthChecker = new ServiceHealthChecker();
                $healthCheckPassed = $healthChecker->check($service);

                if ($healthCheckPassed) {
                    UpdatesSender::sendServiceData($service->getAttribute('type'));
                }

                return true;
            }
        );

        static::saved(
            function (Service $service) {
                event(new ServiceModifiedEvent($service));
            }
        );

        static::deleting(
            function (Service $service) {
                // take the type information and get the config_handler class
                // set the config giving the service id and new config
                $serviceCfg = $service->getConfigHandler();
                if (!empty($serviceCfg) && $serviceCfg::handlesStorage()) {
                    $serviceCfg::removeConfig($service->getKey());
                }

                // ServiceDoc deleted automatically via database foreign key

                return true;
            }
        );

        static::deleted(
            function (Service $service) {
                event(new ServiceDeletedEvent($service));
            }
        );
    }

    public static function create(array $attributes = [])
    {
        // if no label given, use name
        if (empty(array_get($attributes, 'label'))) {
            $attributes['label'] = array_get($attributes, 'name');
        }

        return parent::create($attributes);
    }

    /**
     * @param $name
     *
     * @return null
     */
    public static function getTypeByName($name)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $typeRec = static::whereName($name)->get(['type'])->first();

        return (isset($typeRec, $typeRec['type'])) ? $typeRec['type'] : null;
    }

    /**
     * @return mixed
     */
    public function getConfigAttribute()
    {
        // take the type information and get the config_handler class
        // get and/or format the config given the service id
        $config = $this->getAttributeFromArray('config');
        $config = ($config ? json_decode($config, true) : []);
        if (!empty($serviceCfg = $this->getConfigHandler())) {
            $config = $serviceCfg::getConfig($this->getKey(), $config, $this->protectedView);
        }

        return $config;
    }

    /**
     * @param array|null $value
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public function setConfigAttribute($value)
    {
        $this->config = (array)$value;
        $localConfig = $this->getAttributeFromArray('config');
        $localConfig = ($localConfig ? json_decode($localConfig, true) : []);
        // take the type information and get the config_handler class
        // set the config giving the service id and new config
        if (!empty($serviceCfg = $this->getConfigHandler())) {
            // go ahead and save the config here, otherwise we don't have key yet
            $localConfig = $serviceCfg::setConfig($this->getKey(), $this->config, $localConfig);
            if ($this->isJsonCastable('config') && !is_null($localConfig)) {
                $localConfig = $this->castAttributeAsJson('config', $localConfig);
            }
            $this->attributes['config'] = $localConfig;
        } else {
            if (null !== $typeInfo = ServiceManager::getServiceType($this->type)) {
                if ($subscription = $typeInfo->subscriptionRequired()) {
                    throw new BadRequestException("Provisioning Failed. '$subscription' subscription required for this service type.");
                }
            }
        }
    }

    /**
     * Determine the handler for the extra config settings
     *
     * @return \DreamFactory\Core\Contracts\ServiceConfigHandlerInterface|null
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function getConfigHandler()
    {
        if (null !== $typeInfo = ServiceManager::getServiceType($this->type)) {
            // lookup related service type config model
            return $typeInfo->getConfigHandler();
        }

        return null;
    }

    /**
     * Removes 'config' from field list if supplied as it chokes the model.
     *
     * @param mixed $fields
     *
     * @return array
     */
    public static function cleanFields($fields)
    {
        $fields = parent::cleanFields($fields);

        //If config is requested add id and type as they are need to pull config.
        if (in_array('config', $fields)) {
            $fields[] = 'id';
            $fields[] = 'type';
        }

        //Removing config from field list as it is not a real column in the table.
        if (in_array('config', $fields)) {
            $key = array_keys($fields, 'config');
            unset($fields[$key[0]]);
        }

        return $fields;
    }

    /**
     * If fields is not '*' (all) then remove the empty 'config' property.
     *
     * @param mixed $response
     * @param mixed $fields
     *
     * @return array
     */
    protected static function cleanResult($response, $fields)
    {
        $response = parent::cleanResult($response, $fields);
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        //config is only available when both id and type is present. Therefore only show config if id and type is there.
        if (array_get($fields, 0) !== '*' && (!in_array('type', $fields) || !in_array('id', $fields))) {
            $result = [];

            if (!Arr::isAssoc($response)) {
                foreach ($response as $r) {
                    if (isset($r['config'])) {
                        unset($r['config']);
                    }
                    $result[] = $r;
                }
            } else {
                foreach ($response as $k => $v) {
                    if ('config' === $k) {
                        unset($response[$k]);
                    }
                }
                $result = $response;
            }

            return $result;
        }

        return $response;
    }
}
