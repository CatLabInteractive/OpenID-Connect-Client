<?php

namespace CatLab\OpenIDClient\Helpers;

use CatLab\OpenIDClient\Module;
use Neuron\Application;
use Neuron\Core\Template;
use Neuron\URLBuilder;

class LoginForm {

	/** @var Module */
	private $moduleController;

	/**
	 * @param Module $controller
	 */
	public function __construct (Module $controller)
	{
		$this->moduleController = $controller;
	}

	/**
	 * @return string
	 */
	public function helper ()
	{
		$request = Application::getInstance ()->getRouter ()->getRequest ();

		$template = new Template ('CatLab/OpenIDClient/helpers/welcome.phpt');
		$template->set ('user', $request->getUser ());

		$template->set ('logout', URLBuilder::getURL ($this->moduleController->getRoutePath ()  . '/logout'));
		$template->set ('login', URLBuilder::getURL ($this->moduleController->getRoutePath () . '/login', array ('return' => $request->getUrl ())));

		return $template;
	}
}