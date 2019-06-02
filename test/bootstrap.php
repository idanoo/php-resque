<?php
/**
 * Resque test bootstrap file - sets up a test environment.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->add('Resque_Tests', __DIR__);

# Redis configuration
global $redisTestServer;
$redisTestServer = getenv("REDIS_SERVER") ?? "redis";
Resque::setBackend($redisTestServer);

# Check Redis is accessable locally
try {
    $redisTest = new Resque_Redis($redisTestServer);
} catch (Exception $e) {
    throw new Exception("Unable to connect to redis. Please check there is a redis-server running.");
}
$redisTest = null;



# Cleanup forked workers cleanly
if (function_exists('pcntl_signal')) {
    function sigint()
    {
        exit;
    }

    pcntl_signal(SIGINT, 'sigint');
    pcntl_signal(SIGTERM, 'sigint');
}

# Bootstrap it
class Test_Job
{
    public static $called = false;

    public function perform()
    {
        self::$called = true;
    }
}

class Failing_Job_Exception extends Exception
{

}

class Failing_Job
{
    public function perform()
    {
        throw new Failing_Job_Exception('Message!');
    }
}

class Test_Job_Without_Perform_Method
{

}

class Test_Job_With_SetUp
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


class Test_Job_With_TearDown
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