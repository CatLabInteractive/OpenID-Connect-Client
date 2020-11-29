<?php

namespace CatLab\OpenIDClient\Controllers;

use CatLab\OpenIDClient\Mappers\UserMapper;
use CatLab\OpenIDClient\Models\User;
use InoOicClient\Flow\Basic;
use Neuron\Application;
use Neuron\Config;
use Neuron\Exceptions\ExpectedType;
use Neuron\Exceptions\InvalidParameter;
use Neuron\MapperFactory;
use Neuron\Net\Response;

class LoginController
    extends BaseController
{

    /**
     * These parameters are passed on to the controller.
     * @var string[]
     */
    protected $trackingQueryParameters = [
        'utm_referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content'
    ];

    public function login()
    {
        $cookieGate = $this->cookieGate('Login');
        if ($cookieGate !== true) {
            return $cookieGate;
        }

        // Check for return tag
        if ($return = $this->request->input('return')) {
            $this->request->getSession()->set('post-login-redirect', $return);
        }

        // Check for cancel tag
        if ($return = $this->request->input('cancel')) {
            $this->request->getSession()->set('cancel-login-redirect', $return);
        }

        // Check if already registered
        if ($user = $this->request->getUser('accounts'))
            return $this->module->postLogin($this->request, $user);

        $config = Config::get('openid.client');
        $flow = new Basic (array('client_info' => $config));

        $params = Config::get('openid.client.scope');

        if (!isset($_GET['redirect'])) {
            try {
                $uri = $flow->getAuthorizationRequestUri($params);
                $uri .= $this->getTrackingParameterString();

                if ($this->module->isSendSessionIdAuthCallback()) {
                    $uri .= '&' . $this->request->getSession()->getSessionQueryString();
                }

                return $this->redirectToAuthorization($uri);

            } catch (\Exception $e) {
                printf("Exception during authorization URI creation: [%s] %s", get_class($e), $e->getMessage());
            }
        }

    }

    /**
     * @param $uri
     * @return Response
     */
    protected function redirectToAuthorization($uri)
    {
        return Response::redirect($uri);
        /*
        return Response::template('CatLab/OpenIDClient/redirect.phpt', [
            'redirectUrl' => $uri,
            'layout' => $this->module->getLayout(),
            'tryJavascript' => true
        ]);
        */
    }

    public function next()
    {
        $config = Config::get('openid.client');
        $flow = new Basic (array('client_info' => $config));

        try {
            //$userInfo = $flow->process();

            $authorizationCode = $flow->getAuthorizationCode();
            $accessToken = $flow->getAccessToken($authorizationCode);
            $userInfo = $flow->getUserInfo($accessToken);

            // Get the user
            return $this->processLogin($accessToken, $userInfo);

        } catch (\Exception $e) {
            printf("Exception during user authentication: [%s] %s", get_class($e), $e->getMessage());
        }
    }

    public function logout()
    {
        $config = Config::get('openid.client');
        $flow = new Basic (array('client_info' => $config));

        session_destroy();

        /*
        $template = new Template ('CatLab/Accounts/logout.phpt');

        $template->set ('layout', $this->module->getLayout ());
        $template->set ('action', URLBuilder::getURL ($this->module->getRoutePath () . '/login'));

        return Response::template ($template);
        */

        return $this->module->logout($this->request);
    }

    public function status()
    {
        if ($this->request->getUser()) {
            echo 'logged in!';
        } else {
            echo 'logged out!';
        }
    }

    /**
     * Trigger a series of redirects to make sure we are able to set cookies.
     */
    protected function cookieGate($message)
    {
        $cookies = $this->request->getCookies();

        // we have a cookie!
        if (isset($cookies['cookiegate'])) {
            return true;
        }

        $input = $_GET;
        $cookiegate = isset($input['cookiegate']) ? $input['cookiegate'] : 0;

        $input['cookiegate'] = $cookiegate + 1;
        $redirectUrl = $this->request->getUrl() . '?' . http_build_query($input);

        $cookieSniffer = \CatLab\SameSiteCookieSniffer\Sniffer::instance();

        switch ($cookiegate) {
            // first step, set cookie and do automatic redirect.
            case 0:
                $response = Response::redirect($redirectUrl);
                //$response->setCookies([ 'cookiegate' => '1' ]);
                setcookie('cookiegate', 1, $cookieSniffer->getCookieParameters());
                $response->setStatus(302);
                return $response;

            // oh no, the cookie wasn't set! We need to show an html page :(
            case 1:
                $response = Response::template('CatLab/OpenIDClient/cookiegate/clickNext.phpt', [
                    'redirectUrl' => $redirectUrl,
                    'layout' => $this->module->getLayout(),
                    'tryJavascript' => true
                ]);
                setcookie('cookiegate', 1, $cookieSniffer->getCookieParameters());
                return $response;

            // oh no, the cookie wasn't set! We need to show an html page :(
            case 2:
                $response = Response::template('CatLab/OpenIDClient/cookiegate/clickNext.phpt', [
                    'redirectUrl' => $redirectUrl,
                    'layout' => $this->module->getLayout(),
                    'tryJavascript' => false
                ]);
                setcookie('cookiegate', 1, $cookieSniffer->getCookieParameters());
                return $response;

            // it didn't work. show an error page.
            case 3:
            default:
                return Response::template(
                    'CatLab/OpenIDClient/cookiegate/cookieError.phpt', [
                        'layout' => $this->module->getLayout()
                    ]
                );
        }
    }

    private function processLogin($accessToken, $userdetails)
    {
        if (empty ($userdetails['email'])) {
            throw new InvalidParameter ("Userdetails must contain an email address.");
        }

        if (!isset ($userdetails['verified_email']) || !$userdetails['verified_email']) {

            throw new InvalidParameter ("Email address must be verified.");
        }

        $user = $this->touchUser($accessToken, $userdetails);

        return $this->module->login($this->request, $user);
    }

    private function touchUser($accessToken, $userdetails)
    {
        $mapper = MapperFactory::getUserMapper();
        ExpectedType::check($mapper, UserMapper::class);

        $user = $mapper->getFromSubject($userdetails['id']);

        if (!$user) {

            // First check by email
            $user = $mapper->getFromEmail($userdetails['email']);

            if (!$user) {
                // Create!
                $user = $mapper->getModelInstance();
                $user->setEmail($userdetails['email']);
                $mapper->create($user);
            }

            $user->setSub($userdetails['id']);


        }

        $user->mergeFromInput($userdetails);
        $user->setAccessToken($accessToken);

        $mapper->update($user);


        return $user;
    }

    /**
     * @return string
     */
    protected function getTrackingParameterString()
    {
        $out = [];
        foreach ($this->trackingQueryParameters as $v) {
            if ($this->request->input($v)) {
                $out[$v] = $this->request->input($v);
            }
        }
        if (count($out) === 0) {
            return '';
        }

        return '&' . http_build_query($out);
    }

}
