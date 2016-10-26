<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\RegisterContact;
use DreamFactory\Core\Database\Schema\RelationSchema;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\DataFormatter;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Scalar;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Validator;

/**
 * User
 *
 * @property integer $id
 * @property string  $name
 * @property string  $first_name
 * @property string  $last_name
 * @property string  $email
 * @property string  $phone
 * @property string  $confirm_code
 * @property string  $remember_token
 * @property string  $adldap
 * @property string  $oauth_provider
 * @property string  $security_question
 * @property string  $security_answer
 * @property int     $default_app_id
 * @property boolean $is_active
 * @property boolean $is_sys_admin
 * @property string  $last_login_date
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|User whereId($value)
 * @method static \Illuminate\Database\Query\Builder|User whereName($value)
 * @method static \Illuminate\Database\Query\Builder|User whereFirstName($value)
 * @method static \Illuminate\Database\Query\Builder|User whereLastName($value)
 * @method static \Illuminate\Database\Query\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Query\Builder|User whereIsActive($value)
 * @method static \Illuminate\Database\Query\Builder|User whereIsSysAdmin($value)
 * @method static \Illuminate\Database\Query\Builder|User whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|User whereLastModifiedDate($value)
 */
class User extends BaseSystemModel implements AuthenticatableContract, CanResetPasswordContract
{

    use Authenticatable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'username',
        'first_name',
        'last_name',
        'email',
        'is_active',
        'phone',
        'security_question',
        'security_answer',
        'adldap',
        'oauth_provider',
        'last_login_date',
        'default_app_id'
    ];

    /**
     * Input validation rules.
     *
     * @type array
     */
    protected $rules = [
        'name'  => 'required|max:255',
        'email' => 'required|email|max:255'
    ];

    /**
     * Appended fields.
     *
     * @var array
     */
    protected $appends = ['confirmed', 'expired'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['is_sys_admin', 'password', 'remember_token', 'security_answer'];

    /**
     * Field type casting
     *
     * @var array
     */
    protected $casts = ['is_active' => 'boolean', 'is_sys_admin' => 'boolean', 'id' => 'integer'];

    /**
     * Gets account confirmation status.
     *
     * @return string
     */
    public function getConfirmedAttribute()
    {
        $code = $this->confirm_code;

        if ($code === 'y' || is_null($code)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Shows confirmation expiration.
     *
     * @return bool
     */
    public function getExpiredAttribute()
    {
        return $this->isConfirmationExpired();
    }

    /**
     * Checks to se if confirmation period is expired.
     *
     * @return bool
     */
    public function isConfirmationExpired()
    {
        $ttl = \Config::get('df.confirm_code_ttl', 1440);
        $lastModTime = strtotime($this->last_modified_date);
        $code = $this->confirm_code;

        if ('y' !== $code && !is_null($code) && ((time() - $lastModTime) > ($ttl * 60))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Assigns a role to a user for all apps in the system.
     *
     * @param $user
     * @param $defaultRole
     *
     * @return bool
     * @throws \Exception
     */
    public static function applyDefaultUserAppRole($user, $defaultRole)
    {
        $apps = App::all();

        if (count($apps) === 0) {
            return false;
        }

        foreach ($apps as $app) {
            if (!UserAppRole::whereUserId($user->id)->whereAppId($app->id)->exists()) {
                $userAppRoleData = [
                    'user_id' => $user->id,
                    'app_id'  => $app->id,
                    'role_id' => $defaultRole
                ];

                UserAppRole::create($userAppRoleData);
            }
        }

        return true;
    }

    /**
     * Applies App to Role mapping to a user.
     *
     * @param User    $user
     * @param integer $serviceId
     */
    public static function applyAppRoleMapByService($user, $serviceId)
    {
        $maps = AppRoleMap::whereServiceId($serviceId)->get();

        foreach ($maps as $map) {
            UserAppRole::whereUserId($user->id)->whereAppId($map->app_id)->delete();
            $userAppRoleData = [
                'user_id' => $user->id,
                'app_id'  => $map->app_id,
                'role_id' => $map->role_id
            ];

            UserAppRole::create($userAppRoleData);
        }
    }

    /**
     * @param $password
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public static function validatePassword($password)
    {
        $data = [
            'password' => $password
        ];

        $rule = [
            'password' => 'min:6'
        ];

        $validator = Validator::make($data, $rule);

        if ($validator->fails()) {
            $msg = $validator->errors()->getMessages();
            $errorString = DataFormatter::validationErrorsToString($msg);
            throw new BadRequestException('Invalid data supplied.' . $errorString, null, null, $msg);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function createInternal($record, $params = [])
    {
        try {
            if (!isset($record['name'])) {
                // potentially combine first and last
                $first = (isset($record['first_name']) ? $record['first_name'] : null);
                $last = (isset($record['last_name']) ? $record['last_name'] : null);
                $name = (!empty($first)) ? $first : '';
                $name .= (!empty($name) && !empty($last)) ? ' ' : '';
                $name .= (!empty($last)) ? $last : '';
                if (empty($name)) {
                    // use the first part of their email or the username
                    $email = (isset($record['email']) ? $record['email'] : null);
                    $name = (!empty($email) && strpos($email, '@')) ? strstr($email, '@', true) : '';
                }

                $record['name'] = $name;
            }

            $password = array_get($record, 'password');
            static::validatePassword($password);
            $model = static::create($record);

            if (Scalar::boolval(array_get($params, 'admin')) &&
                Scalar::boolval(array_get($record, 'is_sys_admin'))
            ) {
                $model->is_sys_admin = 1;
            }

            $model->password = $password;
            $model->save();
        } catch (\PDOException $e) {
            throw $e;
        }

        return static::buildResult($model, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function updateInternal($id, $record, $params = [])
    {
        if (empty($record)) {
            throw new BadRequestException('There are no fields in the record to create . ');
        }

        if (empty($id)) {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException('Identifying field "id" can not be empty for update request . ');
        }

        /** @type User $model */
        $model = static::find($id);

        if (!$model instanceof Model) {
            throw new NotFoundException('No resource found for ' . $id);
        }

        $pk = $model->primaryKey;
        //	Remove the PK from the record since this is an update
        ArrayUtils::remove($record, $pk);

        try {
            if ($model->is_sys_admin && !Scalar::boolval(array_get($params, 'admin'))) {
                throw new ForbiddenException('Not allowed to change an admin user.');
            } elseif (Scalar::boolval(array_get($params, 'admin')) && !$model->is_sys_admin) {
                throw new BadRequestException('Cannot update a non-admin user.');
            }

            $password = array_get($record, 'password');
            if (!empty($password)) {
                $model->password = $password;
                static::validatePassword($password);
            }
            $model->update($record);

            return static::buildResult($model, $params);
        } catch (\Exception $ex) {
            if (!$ex instanceof ForbiddenException && !$ex instanceof BadRequestException) {
                throw new InternalServerErrorException('Failed to update resource: ' . $ex->getMessage());
            } else {
                throw $ex;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $attributes = [], array $options = [])
    {
        $oldEmail = $this->email;
        $updated = parent::update($attributes);

        if ($updated && $this->is_sys_admin) {
            $newEmail = $this->email;
            if (('user@example.com' === $oldEmail) && ('user@example.com' !== $newEmail)) {
                // Register user
                RegisterContact::registerUser($this);
            }
        }

        return $updated;
    }

    /**
     * {@inheritdoc}
     */
    public static function deleteInternal($id, $record, $params = [])
    {
        if (empty($record)) {
            throw new BadRequestException('There are no fields in the record to create . ');
        }

        if (empty($id)) {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException('Identifying field "id" can not be empty for update request . ');
        }

        /** @type User $model */
        $model = static::find($id);

        if (!$model instanceof Model) {
            throw new NotFoundException('No resource found for ' . $id);
        }

        try {
            if ($model->is_sys_admin && !Scalar::boolval(array_get($params, 'admin'))) {
                throw new ForbiddenException('Not allowed to delete an admin user.');
            } elseif (Scalar::boolval(array_get($params, 'admin')) && !$model->is_sys_admin) {
                throw new BadRequestException('Cannot delete a non-admin user.');
            } elseif (Session::getCurrentUserId() === $model->id) {
                throw new ForbiddenException('Cannot delete your account.');
            }

            $result = static::buildResult($model, $params);

            $driver = $model->getConnection()->getDriverName();
            if (('sqlsrv' === $driver) || ('dblib' === $driver)) {
                $references = $model->getReferences();
                /** @type RelationSchema $reference */
                foreach ($references as $reference) {
                    if ((RelationSchema::HAS_MANY === $reference->type) &&
                        (('created_by_id' === $reference->refField) ||
                            ('last_modified_by_id' === $reference->refField))
                    ) {
                        $stmt =
                            'update [' .
                            $reference->refTable .
                            '] set [' .
                            $reference->refField .
                            '] = null where [' .
                            $reference->refField .
                            '] = ' .
                            $id;
                        if (0 !== $rows = \DB::update($stmt)) {
                            \Log::debug('found rows: ' . $rows);
                        }
                    } elseif ((RelationSchema::BELONGS_TO === $reference->type) &&
                        (('created_by_id' === $reference->field) ||
                            ('last_modified_by_id' === $reference->field))
                    ) {
                        $stmt =
                            'update [' .
                            $reference->refTable .
                            '] set [' .
                            $reference->field .
                            '] = null where [' .
                            $reference->field .
                            '] = ' .
                            $id . ' and [' . $reference->refField . '] != ' . $id;
                        if (0 !== $rows = \DB::update($stmt)) {
                            \Log::debug('found rows: ' . $rows);
                        }
                    }
                }
            }

            $model->delete();

            return $result;
        } catch (\Exception $ex) {
            if (!$ex instanceof ForbiddenException && !$ex instanceof BadRequestException) {
                throw new InternalServerErrorException('Failed to delete resource: ' . $ex->getMessage());
            } else {
                throw $ex;
            }
        }
    }

    /**
     * Encrypts security answer.
     *
     * @param string $value
     */
    public function setSecurityAnswerAttribute($value)
    {
        $this->attributes['security_answer'] = bcrypt($value);
    }

    /**
     * Encrypts password.
     *
     * @param $password
     */
    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $password = bcrypt($password);
            JWTUtilities::invalidateTokenByUserId($this->id);
        }

        $this->attributes['password'] = $password;
    }

    /**
     * Updates password hash directly.
     * This is used by package import in order to preserve
     * user password during import.
     *
     * @param $hash
     */
    public function updatePasswordHash($hash)
    {
        $this->attributes['password'] = $hash;
        $this->save();
    }

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (User $user){
                if (!$user->is_active) {
                    JWTUtilities::invalidateTokenByUserId($user->id);
                }
                \Cache::forget('user:' . $user->id);
            }
        );

        static::deleted(
            function (User $user){
                JWTUtilities::invalidateTokenByUserId($user->id);
                \Cache::forget('user:' . $user->id);
            }
        );
    }

    /**
     * Returns user info cached, or reads from db if not present.
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
        $cacheKey = 'user:' . $id;
        $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($id){
            $user = static::with('user_lookup_by_user_id')->whereId($id)->first();
            if (empty($user)) {
                throw new NotFoundException("User not found.");
            }

            if (!$user->is_active) {
                throw new ForbiddenException("User is not active.");
            }

            $userInfo = $user->toArray();
            $userInfo['is_sys_admin'] = $user->is_sys_admin;

            return $userInfo;
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
     * @return boolean
     */
    public static function adminExists()
    {
        return \Cache::rememberForever('admin_exists', function (){
            return static::whereIsActive(1)->whereIsSysAdmin(1)->exists();
        });
    }

    public static function resetAdminExists()
    {
        return \Cache::forget('admin_exists');
    }

    /**
     * Creates first admin user.
     *
     * @param  array $data
     *
     * @return User|boolean
     */
    public static function createFirstAdmin(array &$data)
    {
        $validationRules = [
            'name'       => 'required|max:255',
            'first_name' => 'required|max:255',
            'last_name'  => 'required|max:255',
            'email'      => 'required|email|max:255|unique:user',
            'password'   => 'required|confirmed|min:6'
        ];

        $validator = Validator::make($data, $validationRules);

        if ($validator->fails()) {
            $errors = $validator->getMessageBag()->all();
            $data = array_merge($data, ['errors' => $errors, 'version' => \Config::get('df.version')]);

            return false;
        } else {
            /** @type User $user */
            $attributes = array_only($data, ['name', 'first_name', 'last_name', 'email']);
            $attributes['is_active'] = 1;
            $user = static::create($attributes);

            $user->password = array_get($data, 'password');
            $user->is_sys_admin = 1;
            $user->save();

            // Register user
            RegisterContact::registerUser($user);
            // Reset admin_exists flag in cache.
            \Cache::forever('admin_exists', true);

            return $user;
        }
    }
}