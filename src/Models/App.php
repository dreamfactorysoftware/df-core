<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\AppTypes;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\JWTUtilities;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use ServiceManager;

/**
 * App
 *
 * @property integer $id
 * @property string  $name
 * @property string  $api_key
 * @property string  $description
 * @property boolean $is_active
 * @property integer $role_id
 * @property integer $type
 * @property integer $storage_service_id
 * @property string  $storage_component
 * @property string  $path
 * @property string  $url
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static Builder|App whereId($value)
 * @method static Builder|App whereName($value)
 * @method static Builder|App whereApiKey($value)
 * @method static Builder|App whereIsActive($value)
 * @method static Builder|App whereRoleId($value)
 * @method static Builder|App whereStorageServiceId($value)
 * @method static Builder|App whereCreatedDate($value)
 * @method static Builder|App whereLastModifiedDate($value)
 */
class App extends BaseSystemModel
{
    protected $table = 'app';

    protected $fillable = [
        'name',
        'api_key',
        'description',
        'is_active',
        'type',
        'path',
        'url',
        'storage_service_id',
        'storage_container',
        'requires_fullscreen',
        'allow_fullscreen_toggle',
        'toggle_location',
        'role_id'
    ];

    protected $appends = ['launch_url'];

    protected $casts = [
        'is_active'               => 'boolean',
        'requires_fullscreen'     => 'boolean',
        'allow_fullscreen_toggle' => 'boolean',
        'id'                      => 'integer',
        'role_id'                 => 'integer',
        'type'                    => 'integer',
        'storage_service_id'      => 'integer',
    ];

    protected $rules = [
        'api_key' => 'min:64|alpha_num'
    ];

    public static function generateApiKey($name)
    {
        $string = gethostname() . $name . time();
        $key = hash('sha256', $string);

        return $key;
    }

    /**
     * @param       $record
     * @param array $params
     *
     * @return array
     */
    protected static function createInternal($record, $params = [])
    {
        try {
            /** @var App $model */
            $key = array_get($record, 'api_key');
            $uniqueKey = static::isApiKeyUnique($key);
            $model = static::create($record);
            if (empty($key) || !$uniqueKey) {
                $apiKey = static::generateApiKey($model->name);
                $model->api_key = $apiKey;
            }
            $model->save();
        } catch (\PDOException $e) {
            throw $e;
        }

        return static::buildResult($model, $params);
    }

    /**
     * Checks to see if an API Key is uniques or not
     *
     * @param $key string
     *
     * @return bool
     */
    public static function isApiKeyUnique($key)
    {
        $model = static::whereApiKey($key)->first(['id']);

        return (empty($model)) ? true : false;
    }

    public function getLaunchUrlAttribute()
    {
        $launchUrl = '';
        switch ($this->type) {
            case AppTypes::STORAGE_SERVICE:
                if (!empty($this->storage_service_id)) {
                    $service = ServiceManager::getServiceNameById($this->storage_service_id);
                    if (!empty($service)) {
                        $launchUrl .= $service . '/';
                        if (!empty($this->storage_container)) {
                            $launchUrl .= trim($this->storage_container, '/');
                        }
                        if (!empty($this->path)) {
                            $launchUrl .= '/' . ltrim($this->path, '/');
                        }
                        $launchUrl = url($launchUrl);
                    }
                }
                break;

            case AppTypes::PATH:
                $launchUrl = url($this->path);
                break;

            case AppTypes::URL:
                $launchUrl = $this->url;
                break;
        }

        return $launchUrl;
    }

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (App $app){
                if (!$app->is_active) {
                    JWTUtilities::invalidateTokenByAppId($app->id);
                }
                \Cache::forget('app:' . $app->id);
            }
        );

        static::deleted(
            function (App $app){
                JWTUtilities::invalidateTokenByAppId($app->id);
                \Cache::forget('app:' . $app->id);
                \Cache::forget('apikey2appid:' . $app->api_key);
            }
        );
    }

    /**
     * Returns app info cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param int         $id
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getCachedInfo($id, $key = null, $default = null)
    {
        $cacheKey = 'app:' . $id;
        try {
            $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($id){
                $app = App::whereId($id)->first();

                if (empty($app)) {
                    throw new NotFoundException("App not found.");
                }

                if (!$app->is_active) {
                    throw new ForbiddenException("App is not active.");
                }

                return $app->toArray();
            });

            if (is_null($result)) {
                return $default;
            }
        } catch (ModelNotFoundException $ex) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return (isset($result[$key]) ? $result[$key] : $default);
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param string $api_key
     * @param int    $app_id
     */
    public static function setApiKeyToAppId($api_key, $app_id)
    {
        $cacheKey = 'apikey2appid:' . $api_key;
        \Cache::put($cacheKey, $app_id, \Config::get('df.default_cache_ttl'));
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param string $api_key
     *
     * @return int The app id
     */
    public static function getAppIdByApiKey($api_key)
    {
        if (empty($api_key)) {
            return null;
        }
        $cacheKey = 'apikey2appid:' . $api_key;
        try {
            return \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($api_key){
                return App::whereApiKey($api_key)->firstOrFail()->id;
            });
        } catch (ModelNotFoundException $ex) {
            return null;
        }
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param int $id
     *
     * @return string|null The API key for the designated app or null if not found
     */
    public static function getApiKeyByAppId($id)
    {
        if (!empty($id)) {
            // use local app caching
            $key = static::getCachedInfo($id, 'api_key', null);
            if (!is_null($key)) {
                static::setApiKeyToAppId($key, $id);

                return $key;
            }
        }

        return null;
    }

    protected static function getModelFromTable($table)
    {
        if ('lookup' === $table) {
            return AppLookup::class;
        }

        return parent::getModelFromTable($table);
    }
}