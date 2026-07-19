<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture job with a setUp() callback.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class TestJobWithSetUp
{
    public static $called = false;
    public $args = false;
    public $queue;
    public $job;

    public function setUp()
    {
        self::$called = true;
    }

    public function perform()
    {
    }
}
