<?php
/**
 * Created by PhpStorm.
 * User: daedeloth
 * Date: 8/04/15
 * Time: 14:12
 */

namespace CatLab\OpenIDClient\Models;


class Guest
	extends User
	implements \Neuron\Interfaces\Models\Guest {

	public function getId () {
		return null;
	}

	public function getUsername () {
		return 'Guest';
	}

	public function getEmail () {
		return 'nobody@nowhere.com';
	}

}