<?php

namespace CatLab\OpenIDClient;

use CatLab\OpenIDClient\Helpers\LoginForm;
use CatLab\OpenIDClient\Mappers\UserMapper;
use CatLab\OpenIDClient\Models\User;
use Neuron\Application;
use Neuron\Core\Template;
use Neuron\Exceptions\DataNotSet;
use Neuron\Exceptions\ExpectedType;
use Neuron\MapperFactory;
use Neuron\Models\Observable;
use Neuron\Net\Request;
use Neuron\Net\Response;
use Neuron\Router;
use Neuron\URLBuilder;

/**
 * Class Module
 * @package CatLab\OpenIDClient
 */
class Module
	extends Observable
	implements \Neuron\Interfaces\Module {

	private $routepath;

    /** @var string $layout */
    private $layout = 'index.phpt';

    /**
     * Probably a bad idea.
     * @var bool
     */
    private $sendSessionIdAuthCallback = false;

	/**
	 * Set template paths, config vars, etc
	 * @param string $routepath The prefix that should be added to all route paths.
	 * @return void
	 */
	public function initialize ($routepath)
	{
		$this->routepath = $routepath;

		Template::addPath (__DIR__ . '/templates/', 'CatLab/OpenIDClient/');

		// Set session variable
		Application::getInstance ()->on ('dispatch:before', array ($this, 'setRequestUser'));

		// Set the global user mapper, unless one is set already
		Application::getInstance ()->on ('dispatch:first', array ($this, 'setUserMapper'));

		// Add helper methods
		$helper = new LoginForm ($this);
		Template::addHelper ('CatLab.OpenIDClient.LoginForm', $helper);
	}

	/**
	 * @return string
	 */
	public function getRoutePath ()
	{
		return $this->routepath;
	}

	/**
	 * Register the routes required for this module.
	 * @param Router $router
	 * @return void
	 */
	public function setRoutes (Router $router)
	{
		$router
            ->match ('GET|POST', $this->routepath . '/login', '\CatLab\OpenIDClient\Controllers\LoginController@login')
            ->filter('session');

		$router
            ->match ('GET|POST', $this->routepath . '/login/next', '\CatLab\OpenIDClient\Controllers\LoginController@next')
            ->filter('session');

		$router->get ($this->routepath . '/logout', '\CatLab\OpenIDClient\Controllers\LoginController@logout')
            ->filter('session');

		$router->get ($this->routepath . '/status', '\CatLab\OpenIDClient\Controllers\LoginController@status')
            ->filter('session');
	}

    /**
     * @throws ExpectedType
     * @throws \Neuron\Exceptions\InvalidParameter
     */
	public function setUserMapper ()
	{
		try {
			$mapper = MapperFactory::getUserMapper ();
			ExpectedType::check ($mapper, UserMapper::class);
		}
		catch (DataNotSet $e)
		{
			MapperFactory::getInstance ()->setMapper ('user', new UserMapper ());
		}
	}

    /**
     * Login a specific user
     * @param Request $request
     * @param User $user
     * @return \Neuron\Net\Response
     * @throws DataNotSet
     */
	public function login (Request $request, User $user)
	{
		$request->getSession ()->set ('catlab-user-id', $user->getId ());
		$request->getSession ()->set ('catlab-openid-access-token', $user->getAccessToken ());

		$this->trigger ('user:login', $user);

		return $this->postLogin ($request, $user);
	}

	/**
	 * Logout user
	 * @param Request $request
	 * @throws \Neuron\Exceptions\DataNotSet
	 * @return \Neuron\Net\Response
	 */
	public function logout (Request $request)
	{
		$request->getSession ()->set ('catlab-user-id', null);
		$request->getSession ()->set ('catlab-openid-access-token', null);

		$this->trigger ('user:logout');

		return $this->postLogout ($request);
	}

    /**
     * Called right after a user is logged in.
     * Should be a redirect.
     * @param Request $request
     * @param \Neuron\Interfaces\Models\User $user
     * @return \Neuron\Net\Response
     * @throws DataNotSet
     */
	public function postLogin (Request $request, \Neuron\Interfaces\Models\User $user)
	{
		if ($redirect = $request->getSession ()->get ('post-login-redirect'))
		{
			$request->getSession ()->set ('post-login-redirect', null);
			$request->getSession ()->set ('cancel-login-redirect', null);

			return Response::redirect ($redirect);
		}

		return Response::redirect (URLBuilder::getURL ('/'));
	}

	/**
	 * Called after a redirect
	 * @param Request $request
	 * @return Response
	 */
	public function postLogout (Request $request)
	{
		return Response::redirect (URLBuilder::getURL ('/'));
	}

    /**
     * Set user from session
     * @param Request $request
     * @throws \Neuron\Exceptions\InvalidParameter
     */
	public function setRequestUser (Request $request)
	{
		$request->addUserCallback ('accounts', function (Request $request) {

            try {
                $session = $request->getSession();
            } catch (DataNotSet $e) {
                return null;
            }

			$userid = $session->get('catlab-user-id');

			if ($userid)
			{
				$user = MapperFactory::getUserMapper ()->getFromId ($userid);
				ExpectedType::check ($user, User::class);

				if ($user) {

					if ($accessToken = $request->getSession ()->get ('catlab-openid-access-token')) {
						$user->setAccessToken ($accessToken);
					}
					return $user;
				}
			}

			return null;
		});
	}

    /**
     * Set a layout that will be used for all pages
     * @param string $layout
     */
    public function setLayout ($layout)
    {
        $this->layout = $layout;
    }

    /**
     * @return string
     */
    public function getLayout ()
    {
        return $this->layout;
    }

    /**
     * @return bool
     */
    public function isSendSessionIdAuthCallback()
    {
        return $this->sendSessionIdAuthCallback;
    }

    /**
     * @param bool $sendSessionIdAuthCallback
     */
    public function setSendSessionIdAuthCallback($sendSessionIdAuthCallback)
    {
        $this->sendSessionIdAuthCallback = $sendSessionIdAuthCallback;
    }
}
