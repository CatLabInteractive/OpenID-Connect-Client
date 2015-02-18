<?php
/**
 * Created by PhpStorm.
 * User: daedeloth
 * Date: 30/11/14
 * Time: 20:43
 */

namespace CatLab\OpenIDClient\Interfaces;

use CatLab\OpenIDClient\Models\User;

interface UserMapper {

	/**
	 * @param User $user
	 * @return User
	 */
	public function create (User $user);

	/**
	 * @param User $user
	 * @return User
	 */
	public function update (User $user);

	/**
	 * @param $email
	 * @return \CatLab\OpenIDClient\Models\User|null
	 */
	public function getFromEmail ($email);

}