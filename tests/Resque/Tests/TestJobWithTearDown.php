<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture job with a tearDown() callback.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class TestJobWithTearDown
{
    public static $called = false;
    public $args = false;
    public $queue;
    public $job;

    public function perform()
    {
    }

    public function tearDown()
    {
        self::$called = true;
    }
}
