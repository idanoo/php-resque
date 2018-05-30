<?php

/**
 * Resque test case class. Contains setup and teardown methods.
 *
 * @package        Resque/Tests
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Resque_Tests_TestCase extends PHPUnit\Framework\TestCase
{
    protected $resque;
    protected $redis;

    public static function setUpBeforeClass()
    {
        date_default_timezone_set('UTC');
    }

    public function setUp()
    {
        // Setup redis connection for testing.
        $this->redis = new Credis_Client('localhost', '6379');
        Resque::setBackend('localhost');
        $this->redis->flushAll();
    }
}
