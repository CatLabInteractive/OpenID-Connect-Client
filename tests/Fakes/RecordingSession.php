<?php

namespace Tests\Fakes;

use Neuron\Net\Session;
use Neuron\SessionHandlers\SessionHandler;

/**
 * A Session that has regenerate() (as newer Neuron versions do) and
 * records the order of regenerate() and set() calls.
 */
class RecordingSession extends Session
{
    /** @var string[] */
    public $calls = [];

    public function __construct()
    {
        parent::__construct(new SessionHandler());
    }

    public function regenerate()
    {
        $this->calls[] = 'regenerate';
        return true;
    }

    public function set($key, $value)
    {
        $this->calls[] = 'set:' . $key;
        parent::set($key, $value);
    }
}
