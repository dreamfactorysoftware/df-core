<?php namespace DreamFactory\Rave\Components;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Models\User;
use Validator;
use Illuminate\Contracts\Auth\Registrar as RegistrarContract;

class Registrar implements RegistrarContract {

	/**
	 * Get a validator for an incoming registration request.
	 *
	 * @param  array  $data
	 * @return \Illuminate\Contracts\Validation\Validator
	 */
	public function validator(array $data)
	{
		return Validator::make($data, [
			'name' => 'required|max:255',
			'email' => 'required|email|max:255|unique:user',
			'password' => 'required|confirmed|min:6',
		]);
	}

	/**
	 * Create a new user instance after a valid registration.
	 *
	 * @param  array  $data
	 * @return User
	 */
	public function create(array $data)
	{
		$user = User::create([
			'name' => ArrayUtils::get($data, 'name'),
            'first_name' => ArrayUtils::get($data, 'first_name'),
            'last_name' => ArrayUtils::get($data, 'last_name'),
            'is_sys_admin' => ArrayUtils::get($data, 'is_sys_admin', 0),
			'email' => ArrayUtils::get($data, 'email')
		]);

        $user->password = ArrayUtils::get($data, 'password');
        $user->save();

        return $user;
	}

}
