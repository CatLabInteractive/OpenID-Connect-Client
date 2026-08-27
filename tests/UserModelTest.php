<?php

namespace Tests;

use CatLab\OpenIDClient\Models\Guest;
use CatLab\OpenIDClient\Models\User;
use DateTime;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\RecordingUserMapper;

/**
 * Persistence-free behaviour of the User model: what it takes from the
 * provider's userinfo, when it decides to ping accounts, and what anonymize()
 * strips. The mapper is a recording fake (see Tests\Fakes\RecordingUserMapper).
 */
class UserModelTest extends TestCase
{
    /** @var RecordingUserMapper */
    private $mapper;

    protected function setUp(): void
    {
        $this->mapper = RecordingUserMapper::register();
        $this->mapper->reset();
    }

    public function testMergeFromInputOnlyTakesTheUsername()
    {
        $user = new User();
        $user->setEmail('stored@example.com');
        $user->setSub('stored-sub');

        $user->mergeFromInput([
            'id' => 99,
            'username' => 'Provider Name',
            'email' => 'provider@example.com',
            'sub' => 'provider-sub',
        ]);

        $this->assertSame('Provider Name', $user->getDisplayName());
        // Identity fields are owned by the login flow, not by the claims payload.
        $this->assertSame('stored@example.com', $user->getEmail());
        $this->assertSame('stored-sub', $user->getSub());
    }

    public function testMergeFromInputKeepsDisplayNameWhenUsernameIsAbsent()
    {
        $user = new User();
        $user->setDisplayName('Existing');

        $user->mergeFromInput([ 'email' => 'x@example.com' ]);

        $this->assertSame('Existing', $user->getDisplayName());
    }

    public function testShouldPingWhenNeverPinged()
    {
        $user = new User();
        $before = time();

        $this->assertTrue($user->shouldPing());

        $this->assertInstanceOf(DateTime::class, $user->getLastPing());
        $this->assertGreaterThanOrEqual($before, $user->getLastPing()->getTimestamp());
        $this->assertSame([ $user ], $this->mapper->pinged);
        $this->assertSame([], $this->mapper->updated);
    }

    public function testShouldPingWhenLastPingIsOlderThanTheInterval()
    {
        $user = new User();
        $stale = new DateTime('@' . (time() - $user->pingInterval - 60));
        $user->setLastPing($stale);

        $this->assertTrue($user->shouldPing());

        $this->assertNotSame($stale, $user->getLastPing());
        $this->assertGreaterThan($stale->getTimestamp(), $user->getLastPing()->getTimestamp());
        $this->assertSame([ $user ], $this->mapper->pinged);
    }

    public function testShouldNotPingWithinTheInterval()
    {
        $user = new User();
        $recent = new DateTime('@' . (time() - 60));
        $user->setLastPing($recent);

        $this->assertFalse($user->shouldPing());

        $this->assertSame($recent, $user->getLastPing());
        $this->assertSame([], $this->mapper->pinged);
    }

    public function testPingIntervalIsConfigurablePerInstance()
    {
        $user = new User();
        $user->pingInterval = 10;
        $user->setLastPing(new DateTime('@' . (time() - 30)));

        $this->assertTrue($user->shouldPing());
    }

    public function testAnonymizeStripsPersonalDataAndPersists()
    {
        $user = new User();
        $user->setId(5);
        $user->setEmail('person@example.com');
        $user->setDisplayName('Real Name');
        $user->setSub('sub-5');
        $user->setAccessToken('tok');

        $user->anonymize();

        $this->assertNull($user->getEmail());
        $this->assertNull($user->getDisplayName());
        // The link to the accounts server survives; only personal data goes.
        $this->assertSame(5, $user->getId());
        $this->assertSame('sub-5', $user->getSub());
        $this->assertSame([ $user ], $this->mapper->updated);
    }

    public function testGuestIsAnAnonymousUserWithoutId()
    {
        $guest = new Guest();
        $guest->setId(123);
        $guest->setEmail('leak@example.com');
        $guest->setDisplayName('Leak');

        $this->assertInstanceOf(User::class, $guest);
        $this->assertInstanceOf(\Neuron\Interfaces\Models\Guest::class, $guest);
        $this->assertNull($guest->getId());
        $this->assertSame('Guest', $guest->getDisplayName());
        $this->assertSame('nobody@nowhere.com', $guest->getEmail());
    }
}
