<?php
/**
 * Created by PhpStorm.
 * User: daedeloth
 * Date: 30/11/14
 * Time: 15:23
 */

namespace CatLab\OpenIDClient\Models;

class User
	implements \Neuron\Interfaces\Models\User
{

	/** @var int $id */
	private $id;

	/** @var string $email */
	private $email;

	/** @var string $password */
	private $password;

	/** @var string $passwordhash */
	private $passwordhash;

	/** @var string $username */
	private $username;

	/** @var string $sub */
	private $sub;

	public function __construct ()
	{

	}

	/**
	 * Process input from openid provider.
	 * @param $details
	 */
	public function mergeFromInput ($details) {

		if (isset ($details['username']))
			$this->setUsername ($details['username']);

	}

	/**
	 * @return int
	 */
	public function getId ()
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 */
	public function setId ($id)
	{
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getEmail ()
	{
		return $this->email;
	}

	/**
	 * @param string $email
	 */
	public function setEmail ($email)
	{
		$this->email = $email;
	}

	/**
	 * @return string
	 */
	public function getPassword ()
	{
		return $this->password;
	}

	/**
	 * @param string $password
	 */
	public function setPassword ($password)
	{
		$this->password = $password;
	}

	/**
	 * @param string $hash
	 */
	public function setPasswordHash ($hash)
	{
		$this->passwordhash = $hash;
	}

	/**
	 * @return string
	 */
	public function getPasswordHash ()
	{
		return $this->passwordhash;
	}

	/**
	 * @return string
	 */
	public function getUsername ()
	{
		return $this->username;
	}

	/**
	 * @param string $username
	 */
	public function setUsername ($username)
	{
		$this->username = $username;
	}

	/**
	 * @return string
	 */
	public function getSub ()
	{
		return $this->sub;
	}

	/**
	 * @param string $sub
	 */
	public function setSub ($sub)
	{
		$this->sub = $sub;
	}
}