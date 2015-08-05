<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Models\User;
use Validator;
use Illuminate\Contracts\Auth\Registrar as RegistrarContract;

class Registrar implements RegistrarContract
{

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(array $data)
    {
        return Validator::make($data, [
            'name'       => 'required|max:255',
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required|email|max:255|unique:user',
            'password'   => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     *
     * @return User|false
     */
    public function create(array $data)
    {
        $adminExists = User::whereIsActive(1)->whereIsSysAdmin(1)->exists();

        if (!$adminExists) {
            $user = User::create([
                'name'       => ArrayUtils::get($data, 'name'),
                'first_name' => ArrayUtils::get($data, 'first_name'),
                'last_name'  => ArrayUtils::get($data, 'last_name'),
                'is_active'  => 1,
                'email'      => ArrayUtils::get($data, 'email')
            ]);

            $user->password = ArrayUtils::get($data, 'password');
            $user->is_sys_admin = 1;
            $user->save();

            //TODO: Perform user registration here.

            //Reset admin_exists flag in cache.
            User::resetAdminExists();

            return $user;
        }

        return false;
    }
}
