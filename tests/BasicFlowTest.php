<?php

namespace Tests;

use CatLab\OpenIDClient\BasicFlow;
use CatLab\OpenIDClient\Exceptions\OpenIDConnectException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
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
}
