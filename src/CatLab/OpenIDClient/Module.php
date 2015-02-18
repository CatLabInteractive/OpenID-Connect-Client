<?php

namespace CatLab\OpenIDClient;

use Neuron\Router;

class Module
	implements \Neuron\Interfaces\Module {

	private $routepath;

	/**
	 * Set template paths, config vars, etc
	 * @param string $routepath The prefix that should be added to all route paths.
	 * @return void
	 */
	public function initialize ($routepath)
	{
		$this->routepath = $routepath;
	}

	/**
	 * Register the routes required for this module.
	 * @param Router $router
	 * @return void
	 */
	public function setRoutes (Router $router)
	{
		$router->match ('GET|POST', $this->routepath . '/login', '\CatLab\OpenIDClient\Controllers\Login@login');
		$router->match ('GET|POST', $this->routepath . '/login/next', '\CatLab\OpenIDClient\Controllers\Login@next');
	}
}