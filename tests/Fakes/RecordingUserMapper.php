<?php

namespace Tests\Fakes;

use CatLab\OpenIDClient\Interfaces\UserMapper;
use CatLab\OpenIDClient\Models\User;
use Neuron\Exceptions\DataNotSet;
use Neuron\MapperFactory;

/**
 * In-memory stand-in for the database-backed user mapper. Records every
 * persistence call so tests can assert on what the model asked to be stored,
 * without a MySQL connection.
 *
 * Neuron's MapperFactory is a process-wide singleton that refuses to
 * overwrite a registered mapper, so a single instance is registered lazily
 * and shared by all tests; call reset() in setUp().
 */
class RecordingUserMapper implements UserMapper
{
    /** @var User[] */
    public $updated = [];

    /** @var User[] */
    public $pinged = [];

    /** @var User[] */
    public $created = [];

    public static function register(): self
    {
        try {
            $mapper = MapperFactory::getUserMapper();
        } catch (DataNotSet $e) {
            $mapper = new self();
            MapperFactory::getInstance()->setMapper('user', $mapper);
        }

        if (!$mapper instanceof self) {
            throw new \LogicException('A different user mapper is already registered in this process.');
        }

        return $mapper;
    }

    public function reset(): void
    {
        $this->updated = [];
        $this->pinged = [];
        $this->created = [];
    }

    public function create(User $user)
    {
        $this->created[] = $user;
        return $user;
    }

    public function update(User $user)
    {
        $this->updated[] = $user;
        return $user;
    }

    public function updateLastPing(User $user)
    {
        $this->pinged[] = $user;
        return $user;
    }

    public function getFromEmail($email)
    {
        return null;
    }
}
