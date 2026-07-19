<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Resque test bootstrap file - sets up a test environment.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

$loader = require __DIR__ . '/../vendor/autoload.php';

// Redis configuration
$envRedisServer = getenv("REDIS_SERVER");
define('RESQUE_TEST_SERVER', $envRedisServer ? $envRedisServer : "redis");
\Resque\Resque::setBackend(RESQUE_TEST_SERVER);

// Check Redis is accessable locally
try {
    $redisTest = new \Resque\Redis(RESQUE_TEST_SERVER);
} catch (\Exception $e) {
    throw new \Exception("Unable to connect to redis. Please check there is a redis-server running.");
}
$redisTest = null;

// Cleanup forked workers cleanly
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() { exit; });
    pcntl_signal(SIGTERM, function() { exit; });
}

// Test fixture classes (TestJob, FailingJob, TestFailureBackend, ...) live in
// tests/Resque/Tests/ and are loaded on demand via the Resque\Test PSR-4 autoloader.
