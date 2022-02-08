<?php

namespace Resque\Test;

/**
 * Resque test bootstrap file - sets up a test environment.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

$loader = require __DIR__ . '/../vendor/autoload.php';
// $loader->add('Resque_Tests', __DIR__);

# Redis configuration
global $redisTestServer;
$redisTestServer = getenv("REDIS_SERVER") ?: "redis";
\Resque\Resque::setBackend($redisTestServer);

# Check Redis is accessable locally
try {
    $redisTest = new \Resque\Redis($redisTestServer);
} catch (\Exception $e) {
    throw new \Exception("Unable to connect to redis. Please check there is a redis-server running.");
}
$redisTest = null;

# Cleanup forked workers cleanly
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() { exit; });
    pcntl_signal(SIGTERM, function() { exit; });
}

# Bootstrap it
class TestJob
{
    public static $called = false;

    public function perform()
    {
        self::$called = true;
    }
}

class FailingJobException extends \Exception
{
}

class FailingJob
{
    public function perform()
    {
        throw new FailingJobException('Message!');
    }
}

class TestJobWithoutPerformMethod
{
}

class TestJobWithSetUp
{
    public static $called = false;
    public $args = false;

    public function setUp()
    {
        self::$called = true;
    }

    public function perform()
    {
    }
}


class TestJobWithTearDown
{
    public static $called = false;
    public $args = false;

    public function perform()
    {
    }

    public function tearDown()
    {
        self::$called = true;
    }
}