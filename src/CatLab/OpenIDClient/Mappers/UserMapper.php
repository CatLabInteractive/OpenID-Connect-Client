<?php
/**
 * Created by PhpStorm.
 * User: daedeloth
 * Date: 30/11/14
 * Time: 15:20
 */

namespace CatLab\OpenIDClient\Mappers;

use CatLab\OpenIDClient\Collections\UserCollection;
use CatLab\OpenIDClient\Models\Guest;
use CatLab\OpenIDClient\Models\User;
use Neuron\DB\Query;
use Neuron\Exceptions\InvalidParameter;
use Neuron\Mappers\BaseMapper;

class UserMapper
	extends BaseMapper
	implements \CatLab\OpenIDClient\Interfaces\UserMapper
{
	private $table_users;

	/** @var string $error */
	private $error;

	public function __construct ()
	{
		$this->table_users = $this->getTableName ('users');
	}

	/**
	 * @param int $id
	 * @return \CatLab\OpenIDClient\Models\User|null
	 */
	public function getFromId ($id)
	{
		if ($id === -1) {
			return new Guest ();
		}

		$query = new Query
		("
			SELECT
				*
			FROM
				{$this->table_users}
			WHERE
				u_id = ?
		");

		$query->bindValue (1, $id, Query::PARAM_NUMBER);

		return $this->getSingle ($query->execute ());
	}

	/**
	 * @param $email
	 * @return \CatLab\OpenIDClient\Models\User|null
	 */
	public function getFromEmail ($email)
	{
		$query = new Query
		("
			SELECT
				*
			FROM
				{$this->table_users}
			WHERE
				u_email = ?
		");

		$query->bindValue (1, $email);

		return $this->getSingle ($query->execute ());
	}

	/**
	 * @param $sub
	 * @return \CatLab\OpenIDClient\Models\User|null
	 */
	public function getFromSubject ($sub) {
		$query = new Query
		("
			SELECT
				*
			FROM
				{$this->table_users}
			WHERE
				u_sub = ?
		");

		$query->bindValue (1, $sub);

		return $this->getSingle ($query->execute ());
	}

	/**
	 * @param User $user
	 * @return array
	 */
	private function prepareFields (User $user)
	{
		// Prepare data
		$data = array ();

		// Email
        $data['u_email'] = $user->getEmail();

		// Password
		if ($password = $user->getPassword ()) {
            $data['u_password'] = password_hash($password, PASSWORD_DEFAULT);
        } else if ($hash = $user->getPasswordHash ()) {
            $data['u_password'] = $hash;
        }

		// Username
        $data['u_username'] = $user->getUsername ();

		$data['u_sub'] = $user->getSub ();

		if ($accessToken = $user->getAccessToken ()) {
            $data['u_last_access_token'] = $accessToken;
        }

		return $data;
	}

	/**
	 * @param User $user
	 * @throws InvalidParameter
	 * @return \CatLab\OpenIDClient\Models\User
	 */
	public function create (User $user)
	{
		// Check for duplicate
		if ($this->getFromEmail ($user->getEmail ()))
			throw new InvalidParameter ("A user with this email address already exists.");

		$data = $this->prepareFields ($user);

		// Insert
		$id = Query::insert ($this->table_users, $data)->execute ();
		$user->setId ($id);

		return $user;
	}

	/**
	 * @param User $user
	 * @return User
	 */
	public function update (User $user)
	{
		$data = $this->prepareFields ($user);
		Query::update ($this->table_users, $data, array ('u_id' => $user->getId ()))->execute ();
	}

	public function getModelInstance ()
	{
		return new User ();
	}

	/**
	 * Override this to set an alternative object collection.
	 * @return array
	 */
	protected function getObjectCollection ()
	{
		return new UserCollection ();
	}

	/**
	 * @param $data
	 * @return User
	 */
	protected function getObjectFromData ($data)
	{
		$user = $this->getModelInstance ();

		$user->setId (intval ($data['u_id']));

		if ($data['u_email']) {
            $user->setEmail($data['u_email']);
        }

		if ($data['u_password']) {
            $user->setPasswordHash($data['u_password']);
        }

		if ($data['u_username']) {
            $user->setUsername($data['u_username']);
        }

		if ($data['u_last_access_token']) {
            $user->setAccessToken($data['u_last_access_token']);
        }

		if ($data['u_sub']) {
		    $user->setSub($data['u_sub']);
        }

		return $user;
	}

	/**
	 * @return string
	 */
	public function getError ()
	{
		return $this->error;
	}
}
