<?php

namespace Tests;

use CatLab\OpenIDClient\BasicFlow;
use CatLab\OpenIDClient\Exceptions\OpenIDConnectException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BasicFlowTest extends TestCase
{
    private $history = [];

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->history = [];
    }

    private function config(): array
    {
        return [
            'client_id' => 'test-client-id',
            'redirect_uri' => 'https://api.example.com/account/login/next',
            'authorization_endpoint' => 'https://accounts.example.com/oauth2/authorize',
            'token_endpoint' => 'https://accounts.example.com/oauth2/token',
            'user_info_endpoint' => 'https://accounts.example.com/api/1.0/users/me',
            'scope' => 'openid email profile',
            'authentication_info' => [
                'method' => 'client_secret_post',
                'params' => [ 'client_secret' => 'test-secret' ]
            ]
        ];
    }

    private function makeFlow(array $responses): BasicFlow
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new BasicFlow($this->config(), new Client([ 'handler' => $stack ]));
    }

    public function testAuthorizationRequestUri()
    {
        $flow = new BasicFlow($this->config());
        $uri = $flow->getAuthorizationRequestUri('openid email profile');

        $this->assertStringStartsWith('https://accounts.example.com/oauth2/authorize?', $uri);

        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        $this->assertEquals('test-client-id', $query['client_id']);
        $this->assertEquals('https://api.example.com/account/login/next', $query['redirect_uri']);
        $this->assertEquals('code', $query['response_type']);
        $this->assertEquals('openid email profile', $query['scope']);
        $this->assertNotEmpty($query['state']);
        $this->assertEquals($_SESSION[BasicFlow::SESSION_STATE_KEY], $query['state']);
    }

    public function testAuthorizationCodeRoundTrip()
    {
        $flow = new BasicFlow($this->config());
        $uri = $flow->getAuthorizationRequestUri();
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);

        $code = $flow->getAuthorizationCode([ 'code' => 'auth-code-123', 'state' => $query['state'] ]);
        $this->assertEquals('auth-code-123', $code);
    }

    public function testAuthorizationCodeRejectsBadState()
    {
        $flow = new BasicFlow($this->config());
        $flow->getAuthorizationRequestUri();

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'code' => 'auth-code-123', 'state' => 'wrong' ]);
    }

    public function testAuthorizationCodeRejectsErrorResponse()
    {
        $flow = new BasicFlow($this->config());
        $flow->getAuthorizationRequestUri();

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'error' => 'access_denied', 'error_description' => 'nope' ]);
    }

    public function testAuthorizationCodeRejectsMissingCode()
    {
        $flow = new BasicFlow($this->config());
        $uri = $flow->getAuthorizationRequestUri();
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'state' => $query['state'] ]);
    }

    public function testGetAccessToken()
    {
        $flow = $this->makeFlow([
            new Response(200, [], json_encode([ 'access_token' => 'token-abc', 'token_type' => 'Bearer' ]))
        ]);

        $token = $flow->getAccessToken('auth-code-123');
        $this->assertEquals('token-abc', $token);

        $request = $this->history[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('https://accounts.example.com/oauth2/token', (string) $request->getUri());
        $this->assertStringContainsString('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $body);
        $this->assertEquals([
            'client_id' => 'test-client-id',
            'redirect_uri' => 'https://api.example.com/account/login/next',
            'grant_type' => 'authorization_code',
            'code' => 'auth-code-123',
            'client_secret' => 'test-secret'
        ], $body);
    }

    public function testGetAccessTokenThrowsOnErrorResponse()
    {
        $flow = $this->makeFlow([
            new Response(200, [], json_encode([ 'error' => 'invalid_grant' ]))
        ]);

        $this->expectException(OpenIDConnectException::class);
        $flow->getAccessToken('expired-code');
    }

    public function testGetUserInfo()
    {
        $claims = [ 'id' => 42, 'email' => 'user@example.com', 'verified_email' => true ];
        $flow = $this->makeFlow([ new Response(200, [], json_encode($claims)) ]);

        $this->assertEquals($claims, $flow->getUserInfo('token-abc'));

        $request = $this->history[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://accounts.example.com/api/1.0/users/me', (string) $request->getUri());
        $this->assertEquals('Bearer token-abc', $request->getHeaderLine('Authorization'));
    }

    public function testHttpErrorsBecomeOpenIDConnectExceptions()
    {
        $flow = $this->makeFlow([ new Response(500, [], 'oops') ]);

        $this->expectException(OpenIDConnectException::class);
        $flow->getAccessToken('auth-code-123');
    }

    public function testArrayScopeAndResponseTypeAreSpaceSeparated()
    {
        $flow = new BasicFlow($this->config());
        $uri = $flow->getAuthorizationRequestUri([ 'openid', 'email' ], [ 'code', 'id_token' ]);

        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        $this->assertEquals('openid email', $query['scope']);
        $this->assertEquals('code id_token', $query['response_type']);
    }

    public function testEachAuthorizationRequestGetsAFreshState()
    {
        $flow = new BasicFlow($this->config());

        parse_str(parse_url($flow->getAuthorizationRequestUri(), PHP_URL_QUERY), $first);
        parse_str(parse_url($flow->getAuthorizationRequestUri(), PHP_URL_QUERY), $second);

        $this->assertNotEquals($first['state'], $second['state']);
        $this->assertEquals(32, strlen($second['state']));

        // Only the most recent state is valid.
        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'code' => 'c', 'state' => $first['state'] ]);
    }

    public function testStateIsSingleUse()
    {
        $flow = new BasicFlow($this->config());
        parse_str(parse_url($flow->getAuthorizationRequestUri(), PHP_URL_QUERY), $query);
        $callback = [ 'code' => 'auth-code-123', 'state' => $query['state'] ];

        $this->assertEquals('auth-code-123', $flow->getAuthorizationCode($callback));
        $this->assertArrayNotHasKey(BasicFlow::SESSION_STATE_KEY, $_SESSION);

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode($callback);
    }

    public function testAuthorizationCodeRejectsCallbackWithoutPendingRequest()
    {
        $flow = new BasicFlow($this->config());

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'code' => 'auth-code-123', 'state' => 'anything' ]);
    }

    public function testAuthorizationCodeRejectsMissingState()
    {
        $flow = new BasicFlow($this->config());
        $flow->getAuthorizationRequestUri();

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'code' => 'auth-code-123' ]);
    }

    public function testAuthorizationCodeRejectsNonStringStateWithoutTypeError()
    {
        $flow = new BasicFlow($this->config());
        $flow->getAuthorizationRequestUri();

        $this->expectException(OpenIDConnectException::class);
        $flow->getAuthorizationCode([ 'code' => 'auth-code-123', 'state' => [ 'injected' ] ]);
    }

    public function testAuthorizationCodeReadsGetByDefault()
    {
        $flow = new BasicFlow($this->config());
        parse_str(parse_url($flow->getAuthorizationRequestUri(), PHP_URL_QUERY), $query);

        $_GET = [ 'code' => 'from-get', 'state' => $query['state'] ];
        try {
            $this->assertEquals('from-get', $flow->getAuthorizationCode());
        } finally {
            $_GET = [];
        }
    }

    public function testAuthorizationErrorMessageIncludesDescription()
    {
        $flow = new BasicFlow($this->config());
        $flow->getAuthorizationRequestUri();

        try {
            $flow->getAuthorizationCode([ 'error' => 'access_denied', 'error_description' => 'User said no' ]);
            $this->fail('Expected an OpenIDConnectException');
        } catch (OpenIDConnectException $e) {
            $this->assertStringContainsString('access_denied', $e->getMessage());
            $this->assertStringContainsString('User said no', $e->getMessage());
        }
    }

    public function testGetAccessTokenThrowsWhenTokenIsMissing()
    {
        $flow = $this->makeFlow([
            new Response(200, [], json_encode([ 'token_type' => 'Bearer', 'expires_in' => 3600 ]))
        ]);

        $this->expectException(OpenIDConnectException::class);
        $this->expectExceptionMessage('No access_token');
        $flow->getAccessToken('auth-code-123');
    }

    public function testGetAccessTokenPrefersErrorOverToken()
    {
        $flow = $this->makeFlow([
            new Response(200, [], json_encode([ 'error' => 'invalid_client', 'access_token' => 'should-not-be-used' ]))
        ]);

        $this->expectException(OpenIDConnectException::class);
        $this->expectExceptionMessage('invalid_client');
        $flow->getAccessToken('auth-code-123');
    }

    /**
     * @dataProvider nonObjectBodies
     */
    public function testNonObjectBodiesAreRejected(string $body)
    {
        $flow = $this->makeFlow([ new Response(200, [], $body) ]);

        $this->expectException(OpenIDConnectException::class);
        $this->expectExceptionMessage('invalid JSON');
        $flow->getUserInfo('token-abc');
    }

    public function nonObjectBodies(): array
    {
        return [
            'html' => [ '<html>Sign in</html>' ],
            'empty' => [ '' ],
            'json null' => [ 'null' ],
            'json string' => [ '"token-abc"' ],
            'json number' => [ '42' ],
        ];
    }

    public function testGetUserInfoHttpErrorBecomesOpenIDConnectException()
    {
        $flow = $this->makeFlow([ new Response(401, [], json_encode([ 'error' => 'invalid_token' ])) ]);

        $this->expectException(OpenIDConnectException::class);
        $flow->getUserInfo('expired-token');
    }

    public function testTransportFailuresKeepTheGuzzleCause()
    {
        $flow = $this->makeFlow([
            new ConnectException('Connection refused', new Request('POST', 'https://accounts.example.com/oauth2/token'))
        ]);

        try {
            $flow->getAccessToken('auth-code-123');
            $this->fail('Expected an OpenIDConnectException');
        } catch (OpenIDConnectException $e) {
            $this->assertInstanceOf(GuzzleException::class, $e->getPrevious());
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    public function testClientSecretIsOnlySentInTheBody()
    {
        $flow = $this->makeFlow([
            new Response(200, [], json_encode([ 'access_token' => 'token-abc' ]))
        ]);
        $flow->getAccessToken('auth-code-123');

        $request = $this->history[0]['request'];
        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertStringNotContainsString('test-secret', (string) $request->getUri());
        $this->assertStringContainsString('client_secret=test-secret', (string) $request->getBody());
    }
}
