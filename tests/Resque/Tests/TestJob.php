<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture job that records when it is performed.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class TestJob
{
    public static $called = false;
    public $args = false;
    public $queue;
    public $job;

    public function perform()
    {
        self::$called = true;
    }
}
