<?php

namespace CatLab\OpenIDClient\Controllers;

use InoOicClient\Flow\Basic;
use Neuron\Config;

class Login {

	public function login ()
	{
		$config = Config::get ('openid.client');
		$flow = new Basic (array ('client_info' => $config));

		if (! isset($_GET['redirect'])) {
			try {
				$uri = $flow->getAuthorizationRequestUri(Config::get ('openid.client.scope'));
				header ('Location: ' . $uri);

				printf("<a href=\"%s\">Login</a>", $uri);

			} catch (\Exception $e) {
				printf("Exception during authorization URI creation: [%s] %s", get_class($e), $e->getMessage());
			}
		}

	}

	public function next ()
	{
		$config = Config::get ('openid.client');
		$flow = new Basic (array ('client_info' => $config));

		try {
			$userInfo = $flow->process();

			print_r ($userInfo);

		} catch (\Exception $e) {
			printf("Exception during user authentication: [%s] %s", get_class($e), $e->getMessage());
		}
	}

}