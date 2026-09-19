<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Stub Credis driver that fails a set number of calls before succeeding, and counts
 * reconnects, so \Resque\Redis retry behaviour can be tested without a real server.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class FlakyRedisDriver
{
    /** @var int Reconnects performed */
    public $reconnects = 0;

    /** @var array<int, bool> The $force argument of each close() call, in order */
    public $closes = [];

    /** @var array<int, string> Commands the driver was asked to run, in order */
    public $calls = [];

    /** @var int Remaining calls to fail */
    private $failures;

    /** @var \Exception Thrown while $failures remain */
    private $error;

    public function __construct(int $failures, \Exception $error)
    {
        $this->failures = $failures;
        $this->error = $error;
    }

    public function close($force = false): bool
    {
        $this->closes[] = (bool)$force;

        return true;
    }

    public function connect(): self
    {
        $this->reconnects++;

        return $this;
    }

    public function __call($name, $args)
    {
        $this->calls[] = $name;
        if ($this->failures > 0) {
            $this->failures--;
            throw $this->error;
        }

        return 'ok';
    }
}
