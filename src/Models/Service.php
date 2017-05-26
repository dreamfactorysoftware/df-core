<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\DsnToConnectionConfig;
use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Events\ServiceDeletedEvent;
use DreamFactory\Core\Events\ServiceModifiedEvent;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use ServiceManager;
use Symfony\Component\Yaml\Yaml;

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

    protected $fillable = ['name', 'label', 'description', 'is_active', 'type', 'config', 'doc'];

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

    protected $appends = ['doc'];

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

    /**
     * @var array Live API Documentation
     */
    protected $doc;

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
                    // take the type information and get the config_handler class
                    // set the config giving the service id and new config
                    $serviceCfg = $service->getConfigHandler();
                    if (!empty($serviceCfg) && $serviceCfg::handlesStorage()) {
                        $serviceCfg::storeConfig($service->getKey(), $service->config);
                    }
                }
                if (!empty($service->doc)) {
                    $service->doc['service_id'] = $service->id;
                    ServiceDoc::create($service->doc);
                }

                return true;
            }
        );

        static::saved(
            function (Service $service) {
                \Cache::forget('service:' . $service->name);
                \Cache::forget('service_id:' . $service->id);

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
                \Cache::forget('service:' . $service->name);
                \Cache::forget('service_id:' . $service->id);

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

    public function getDocAttribute()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!empty($doc = ServiceDoc::find($this->id))) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->doc = $doc->toArray();
        } else {
            if (!empty($serviceType = ServiceManager::getServiceType($this->type))) {
                $this->doc = $serviceType->getDefaultApiDoc($this);
            }
        }

        return $this->doc;
    }

    /**
     * @param array $val
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public function setDocAttribute($val)
    {
        $val = (array)$val;
        $this->doc = $val;
        if ($this->exists) {
            /** @noinspection PhpUndefinedMethodInspection */
            $model = ServiceDoc::find($this->id);
            if (!empty($val)) {
                if (!empty($model)) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $model->update($val);
                } else {
                    $val['service_id'] = $this->id;
                    ServiceDoc::create($val);
                }
            } elseif (!empty($model)) {
                // delete it
                /** @noinspection PhpUndefinedMethodInspection */
                $model->delete();
            }
        }
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
     * @return array
     */
    public static function available()
    {
        // need to cache this possibly
        /** @noinspection PhpUndefinedMethodInspection */
        return static::pluck('name')->all();
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
                if ($typeInfo->isSubscriptionRequired()) {
                    throw new BadRequestException("Provisioning Failed. Subscription required for this service type.");
                }
            }
        }
    }

    public static function storedContentToArray($content, $format, $service_info = [])
    {
        // replace service placeholders with value for this service instance
        if (!empty($name = data_get($service_info, 'name'))) {
            $lcName = strtolower($name);
            $ucwName = camelize($name);
            $pluralName = str_plural($name);
            $pluralUcwName = str_plural($ucwName);

            $content = str_replace(
                ['{service.name}', '{service.names}', '{service.Name}', '{service.Names}'],
                [$lcName, $pluralName, $ucwName, $pluralUcwName],
                $content);
        }
        if (!empty($label = data_get($service_info, 'label'))) {
            $content = str_replace('{service.label}', $label, $content);
        }
        if (!empty($description = data_get($service_info, 'description'))) {
            $content = str_replace('{service.description}', $description, $content);
        }

        switch ($format) {
            case ApiDocFormatTypes::SWAGGER_JSON:
                $content = json_decode($content, true);
                break;
            case ApiDocFormatTypes::SWAGGER_YAML:
                $content = Yaml::parse($content);
                break;
            default:
                throw new InternalServerErrorException("Invalid API Doc Format '$format'.");
        }

        if (!empty($name)) {
            $paths = array_get($content, 'paths', []);
            // tricky here, loop through all indexes to check if all start with service name,
            // otherwise need to prepend service name to all.
            if (!empty(array_filter(array_keys($paths), function ($k) use ($name) {
                $k = ltrim($k, '/');
                if (false !== strpos($k, '/')) {
                    $k = strstr($k, '/', true);
                }

                return (0 !== strcasecmp($name, $k));
            }))
            ) {
                $newPaths = [];
                foreach ($paths as $path => $pathDef) {
                    $newPath = '/' . $name . $path;
                    $newPaths[$newPath] = $pathDef;
                }
                $paths = $newPaths;
            }
            // make sure each path is tagged
            foreach ($paths as $path => &$pathDef) {
                foreach ($pathDef as $verb => &$verbDef) {
                    // If we leave the incoming tags, they get bubbled up to our service-level
                    // and possibly confuse the whole interface. Replace with our service name tag.
//                    if (!is_array($tag = array_get($verbDef, 'tags', []))) {
//                        $tag = [];
//                    }
//                    if (false === array_search($name, $tag)) {
//                        $tag[] = $name;
//                        $verbDef['tags'] = $tag;
//                    }
                    switch (strtolower($verb)) {
                        case 'get':
                        case 'post':
                        case 'put':
                        case 'patch':
                        case 'delete':
                        case 'options':
                        case 'head':
                            $verbDef['tags'] = [$name];
                            break;
                    }
                }
            }
            $content['paths'] = $paths; // write any changes back
        }

        return $content;
    }

    /**
     * Returns service info cached by service name, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param string      $name
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getCachedByName($name, $key = null, $default = null)
    {
        $cacheKey = 'service:' . $name;
        $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($name) {
            /** @var Service $service */
            $service = static::whereName($name)->first();
            if (empty($service)) {
                throw new NotFoundException("Could not find a service for $name");
            }

            $service->protectedView = false;

            $content = null;
            if (!empty($doc = $service->getDocAttribute())) {
                if (is_array($doc) && !empty($content = array_get($doc, 'content'))) {
                    if (is_string($content)) {
                        $content = static::storedContentToArray($content, array_get($doc, 'format'), $service);
                    }
                }
            }

            $settings = $service->toArray();
            if (isset($content)) {
                $settings['doc'] = $content;
            }

            return $settings;
        });

        if (is_null($result)) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return (isset($result[$key]) ? $result[$key] : $default);
    }

    /**
     * Returns service info cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param int         $id
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getCachedById($id, $key = null, $default = null)
    {
        $name = static::getCachedNameById($id);

        return static::getCachedByName($name, $key, $default);
    }

    /**
     * Returns service name cached, or reads from db if not present.
     *
     * @param int $id
     *
     * @return string|null
     */
    public static function getCachedNameById($id)
    {
        $cacheKey = 'service_id:' . $id;

        return \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($id) {
            $name = static::whereId($id)->value('name');

            if (empty($name)) {
                throw new NotFoundException("Could not find a service for id $id");
            }

            return $name;
        });
    }

    /**
     * Returns service id cached, or reads from db if not present.
     *
     * @param string $name
     *
     * @return integer|null
     */
    public static function getCachedIdByName($name)
    {
        $cacheKey = 'service_name:' . $name;

        return \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($name) {
            $id = static::whereName($name)->value('id');

            if (empty($id)) {
                throw new NotFoundException("Could not find a service for name $name");
            }

            return $id;
        });
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