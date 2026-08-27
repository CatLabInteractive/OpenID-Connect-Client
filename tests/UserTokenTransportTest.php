<?php

namespace Tests;

use CatLab\OpenIDClient\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Server-to-server calls made on behalf of the user (ping, activity) must
 * carry the access token in the Authorization header, never in the query
 * string: accounts.catlab.eu is dropping query-string bearer tokens (they
 * end up in access logs; security audit 2026-08-27, A11).
 */
class UserTokenTransportTest extends TestCase
{
    public function testTheTokenTravelsInTheAuthorizationHeaderOnly()
    {
        $user = new User();
        $user->setAccessToken('tok-123');

        $request = $user->createAuthenticatedRequest('https://accounts.example.com/api/1.0/users/me/ping');

        $this->assertSame('https://accounts.example.com/api/1.0/users/me/ping', $request->getUrl());
        $this->assertSame('Bearer tok-123', $request->getHeaders()['Authorization']);
        $this->assertArrayNotHasKey('access_token', (array)$request->getParameters());
    }
}
