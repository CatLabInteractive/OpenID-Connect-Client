<?php

namespace CatLab\OpenIDClient\Controllers;

use CatLab\OpenIDClient\Mappers\UserMapper;
use CatLab\OpenIDClient\Models\User;
use InoOicClient\Flow\Basic;
use Neuron\Config;
use Neuron\Exceptions\ExpectedType;
use Neuron\MapperFactory;

class LoginController
	extends BaseController {

	public function login ()
	{
		$config = Config::get ('openid.client');
		$flow = new Basic (array ('client_info' => $config));

		$params = Config::get ('openid.client.scope');

		if (! isset($_GET['redirect'])) {
			try {
				$uri = $flow->getAuthorizationRequestUri ($params);
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

			// Get the user
			return $this->processLogin ($userInfo);

		} catch (\Exception $e) {
			printf("Exception during user authentication: [%s] %s", get_class($e), $e->getMessage());
		}
	}

	public function logout ()
	{
		/*
		$template = new Template ('CatLab/Accounts/logout.phpt');

		$template->set ('layout', $this->module->getLayout ());
		$template->set ('action', URLBuilder::getURL ($this->module->getRoutePath () . '/login'));

		return Response::template ($template);
		*/

		return $this->module->logout ($this->request);
	}

	public function status ()
	{
		if ($this->request->getUser ()) {
			echo 'logged in!';
		}
		else {
			echo 'logged out!';
		}
	}

	private function processLogin ($userdetails)
	{
		$mapper = MapperFactory::getUserMapper ();
		ExpectedType::check ($mapper, UserMapper::class);

		$user = $mapper->getFromEmail ($userdetails['email']);

		if (!$user) {
			// Create!
			$user = new User ();
			$user->setEmail ($userdetails['email']);
			$mapper->create ($user);
		}

		$user->mergeFromInput ($userdetails);
		$mapper->update ($user);

		return $this->module->login ($this->request, $user);
	}

}