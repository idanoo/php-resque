<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture failure backend that records the last failure it received.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class TestFailureBackend implements \Resque\Failure\ResqueFailureInterface
{
    public static $payload;
    public static $exception;
    public static $worker;
    public static $queue;

    public function __construct($payload, $exception, $worker, $queue)
    {
        self::$payload = $payload;
        self::$exception = $exception;
        self::$worker = $worker;
        self::$queue = $queue;
    }
}
