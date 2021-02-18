<?php

namespace Resque\Test;

/**
 * Resque test case class. Contains setup and teardown methods.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    protected $resque;
    protected $redis;

    public static function setUpBeforeClass(): void
    {
        date_default_timezone_set('UTC');
    }

    public function setUp(): void
    {
        // Setup redis connection for testing.
        global $redisTestServer;

        $this->redis = new \Credis_Client($redisTestServer, '6379');
        \Resque\Resque::setBackend($redisTestServer);
        $this->redis->flushAll();
    }
}
