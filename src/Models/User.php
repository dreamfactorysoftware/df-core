<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\RegisterContact;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
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
 * @property string  $description
 * @property boolean $is_active
 * @property boolean $is_sys_admin
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
     * @type array
     */
    protected $rules = [
        'name'  => 'required|max:255',
        'email' => 'required|email|max:255'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['is_sys_admin', 'password', 'remember_token', 'security_answer'];

    protected $casts = ['is_active' => 'boolean', 'is_sys_admin' => 'boolean', 'id' => 'integer'];

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
     * @param $value
     *
     * @return string
     */
    public function getEmailAttribute($value)
    {
        if (false !== strpos($value, '+')) {
            list($emailId, $domain) = explode('@', $value);
            list($emailId, $provider) = explode('+', $emailId);

            $value = $emailId . '@' . $domain;
        }

        return $value;
    }

    /**
     * @param $email
     */
    public function setEmailAttribute($email)
    {
        if ($this->exists && !empty($email)) {
            $provider = (!empty($this->oauth_provider)) ? $this->oauth_provider : $this->adldap;
            if (!empty($provider)) {
                list($emailId, $domain) = explode('@', $email);
                $emailId .= '+' . $provider;
                $email = $emailId . '@' . $domain;
            }
        }

        $this->attributes['email'] = $email;
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

            $model = static::create($record);

            if (ArrayUtils::getBool($params, 'admin') &&
                ArrayUtils::getBool($record, 'is_sys_admin')
            ) {
                $model->is_sys_admin = 1;
            }

            $model->password = ArrayUtils::get($record, 'password');
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
            if ($model->is_sys_admin && !ArrayUtils::getBool($params, 'admin')) {
                throw new ForbiddenException('Not allowed to change an admin user.');
            } elseif (ArrayUtils::getBool($params, 'admin') && !$model->is_sys_admin) {
                throw new BadRequestException('Cannot update a non-admin user.');
            }

            $password = ArrayUtils::get($record, 'password');
            if (ArrayUtils::getBool($params, 'admin') && !empty($password)) {
                $model->password = ArrayUtils::get($record, 'password');
            }

            $oldEmail = $model->email;

            $model->update($record);

            if (('user@example.com' === $oldEmail) && ('user@example.com' !== $model->email)) {
                // Register user
                RegisterContact::registerUser($model);
            }

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
            if ($model->is_sys_admin && !ArrayUtils::getBool($params, 'admin')) {
                throw new ForbiddenException('Not allowed to delete an admin user.');
            } elseif (ArrayUtils::getBool($params, 'admin') && !$model->is_sys_admin) {
                throw new BadRequestException('Cannot delete a non-admin user.');
            } elseif (Session::getCurrentUserId() === $model->id) {
                throw new ForbiddenException('Cannot delete your account.');
            }

            $result = static::buildResult($model, $params);
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
        }

        $this->attributes['password'] = $password;
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
            ArrayUtils::set($userInfo, 'is_sys_admin', $user->is_sys_admin);

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

            $user->password = ArrayUtils::get($data, 'password');
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