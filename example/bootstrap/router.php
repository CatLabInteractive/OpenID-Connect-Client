<?php

// Initialize router
$router = new \Neuron\Router ();

// Accounts module
$signinmodule = new \CatLab\Accounts\Module ();

$password = new \CatLab\Accounts\Authenticators\Password ();
$signinmodule->addAuthenticator ($password);
$signinmodule->setLayout ('index-account.phpt');

// Make the module available on /account
$router->module ('/account', $signinmodule);

// Catch the default route
$router->get ('/', function () {
	return \Neuron\Net\Response::template ('home.phpt');
});

return $router;