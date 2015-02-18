<?php

namespace CatLab\OpenIDClient\Controllers;

use CatLab\OpenIDClient\Mappers\UserMapper;
use CatLab\OpenIDClient\Models\User;
use InoOicClient\Flow\Basic;
use Neuron\Config;
use Neuron\Exceptions\ExpectedType;
use Neuron\Exceptions\InvalidParameter;
use Neuron\MapperFactory;

class LoginController
	extends BaseController {

	public function login ()
	{
		// Check for return tag
		if ($return = $this->request->input ('return')) {
			$this->request->getSession ()->set ('post-login-redirect', $return);
		}

		// Check for cancel tag
		if ($return = $this->request->input ('cancel')) {
			$this->request->getSession ()->set ('cancel-login-redirect', $return);
		}

		// Check if already registered
		if ($user = $this->request->getUser ('accounts'))
			return $this->module->postLogin ($this->request, $user);

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
		if (empty ($userdetails['email'])) {
			throw new InvalidParameter ("Userdetails must contain an email address.");
		}

		if (!isset ($userdetails['verified_email']) || !$userdetails['verified_email']) {

			var_dump ($userdetails);
			throw new InvalidParameter ("Email address must be verified.");
		}

		$user = $this->touchUser ($userdetails);
		return $this->module->login ($this->request, $user);
	}

	private function touchUser ($userdetails)
	{
		$mapper = MapperFactory::getUserMapper ();
		ExpectedType::check ($mapper, UserMapper::class);

		$user = $mapper->getFromSubject ($userdetails['sub']);

		if (!$user) {

			// First check by email
			$user = $mapper->getFromEmail ($userdetails['email']);

			if (!$user) {
				// Create!
				$user = new User ();
				$user->setEmail ($userdetails['email']);
				$mapper->create ($user);
			}

			$user->setSub ($userdetails['sub']);
		}

		$user->mergeFromInput ($userdetails);
		$mapper->update ($user);



		return $user;
	}

}