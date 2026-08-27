<?php

namespace Tests;

use CatLab\OpenIDClient\Controllers\LoginController;
use CatLab\OpenIDClient\Module;
use Neuron\Net\Request;
use Neuron\Net\Response;
use PHPUnit\Framework\TestCase;

/**
 * The cookie gate counts its redirect hops through the ?cookiegate= query
 * parameter, which is attacker-controlled. A leading-numeric value such as
 * "1'" (seen in production) used to be added to as-is, raising
 * "A non-numeric value encountered" and falling through to the error page.
 */
class LoginControllerCookieGateTest extends TestCase
{
    /** @var array */
    private $getBackup;

    protected function setUp(): void
    {
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
    }

    private function controller(): ExposedLoginController
    {
        $controller = new ExposedLoginController(new Module());

        $request = new Request();
        $request->setUrl('https://api.example.com/account/login');
        $request->setCookies([]);
        $controller->setRequest($request);

        return $controller;
    }

    public function testMalformedStepDoesNotRaiseWarnings()
    {
        $_GET = [ 'cookiegate' => "3'", 'return' => '/' ];

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_WARNING | E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $response = $this->controller()->callCookieGate('Login');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'cookiegate must be parsed as an integer before arithmetic');
        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * @dataProvider stepProvider
     */
    public function testStepIsParsedAsInteger($raw, int $expected)
    {
        $_GET = $raw === null ? [] : [ 'cookiegate' => $raw ];

        $this->assertSame($expected, $this->controller()->callCookieGateStep($_GET));
    }

    public function stepProvider(): array
    {
        return [
            'missing' => [ null, 0 ],
            'plain' => [ '2', 2 ],
            'leading numeric' => [ "1'", 1 ],
            'garbage' => [ 'abc', 0 ],
            'array' => [ [ '1' ], 0 ],
        ];
    }
}

class ExposedLoginController extends LoginController
{
    public function callCookieGate($message)
    {
        return $this->cookieGate($message);
    }

    public function callCookieGateStep(array $input)
    {
        return $this->getCookieGateStep($input);
    }
}
