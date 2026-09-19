<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Worker whose first job reservation fails as though Redis were unreachable, then
 * shuts itself down, so the work loop's outage handling can be tested.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class FlakyRedisWorker extends \Resque\Worker
{
    /** @var int Reservation attempts made */
    public $reserveCalls = 0;

    public function reserve($blocking = false, $timeout = null)
    {
        $this->reserveCalls++;
        if ($this->reserveCalls === 1) {
            throw new \Resque\RedisException(
                'Error communicating with Redis: read error on connection to tcp://redis.internal:6379'
            );
        }

        $this->shutdown();

        return false;
    }
}
