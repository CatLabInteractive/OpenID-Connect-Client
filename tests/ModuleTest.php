<?php

namespace Tests;

use CatLab\OpenIDClient\Models\User;
use CatLab\OpenIDClient\Module;
use Neuron\Net\Request;
use Neuron\Net\Response;
use Neuron\Net\Session;
use Neuron\SessionHandlers\SessionHandler;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\RecordingSession;

/**
 * Session bookkeeping done by the module on login/logout. Neuron's Session
 * reads and writes $_SESSION directly, so no session_start() is needed.
 */
class ModuleTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function request(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new SessionHandler()));
        return $request;
    }

    private function user(int $id, string $token): User
    {
        $user = new User();
        $user->setId($id);
        $user->setAccessToken($token);
        return $user;
    }

    public function testLoginStoresIdentityInSessionAndNotifiesListeners()
    {
        $module = new Module();
        $seen = [];
        $module->on('user:login', function (User $user) use (&$seen) {
            $seen[] = $user;
        });

        $user = $this->user(7, 'access-token-7');
        $response = $module->login($this->request(), $user);

        $this->assertSame(7, $_SESSION['catlab-user-id']);
        $this->assertSame('access-token-7', $_SESSION['catlab-openid-access-token']);
        $this->assertSame([ $user ], $seen);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(302, $response->getStatus());
    }

    public function testLoginRegeneratesTheSessionIdBeforeStoringTheIdentity()
    {
        $_SESSION['post-login-redirect'] = 'https://app.example.com/after-login';

        $session = new RecordingSession();
        $request = new Request();
        $request->setSession($session);

        $response = (new Module())->login($request, $this->user(7, 'tok'));

        $this->assertSame('regenerate', $session->calls[0]);
        $this->assertSame(1, count(array_keys($session->calls, 'regenerate')));
        $this->assertSame(
            [ 'regenerate', 'set:catlab-user-id', 'set:catlab-openid-access-token' ],
            array_slice($session->calls, 0, 3)
        );

        // What was in the session before login is still there afterwards.
        $this->assertSame(7, $_SESSION['catlab-user-id']);
        $this->assertSame('https://app.example.com/after-login', $response->getHeaders()['Location']);
    }

    public function testLoginWithoutAnActiveSessionDoesNotStartOne()
    {
        $this->assertSame(PHP_SESSION_NONE, session_status());

        (new Module())->login($this->request(), $this->user(7, 'tok'));

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(7, $_SESSION['catlab-user-id']);
    }

    public function testLoginRedirectsToTheStoredReturnUrlOnce()
    {
        $_SESSION['post-login-redirect'] = 'https://app.example.com/after-login';
        $_SESSION['cancel-login-redirect'] = 'https://app.example.com/cancelled';

        $module = new Module();
        $response = $module->login($this->request(), $this->user(7, 'tok'));

        $this->assertSame('https://app.example.com/after-login', $response->getHeaders()['Location']);
        // Consumed: a second login must not bounce to the stale URL again.
        $this->assertNull($_SESSION['post-login-redirect']);
        $this->assertNull($_SESSION['cancel-login-redirect']);

        $again = $module->login($this->request(), $this->user(7, 'tok'));
        $this->assertNotEquals('https://app.example.com/after-login', $again->getHeaders()['Location']);
    }

    public function testLoginFallsBackToTheApplicationRoot()
    {
        $module = new Module();
        $response = $module->login($this->request(), $this->user(7, 'tok'));

        $this->assertSame('/', $response->getHeaders()['Location']);
    }

    public function testLogoutClearsIdentityAndNotifiesListeners()
    {
        $_SESSION['catlab-user-id'] = 7;
        $_SESSION['catlab-openid-access-token'] = 'access-token-7';

        $module = new Module();
        $events = 0;
        $module->on('user:logout', function () use (&$events) {
            $events++;
        });

        $response = $module->logout($this->request());

        $this->assertNull($_SESSION['catlab-user-id']);
        $this->assertNull($_SESSION['catlab-openid-access-token']);
        $this->assertSame(1, $events);
        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/', $response->getHeaders()['Location']);
    }
}
