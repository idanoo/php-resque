<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture job implementing the job interface.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class SomeJobClass implements \Resque\Job\JobInterface
{
    public static $called = false;
    public $args = false;
    public $queue;
    public $job;

    /**
     * @return bool
     */
    public function perform()
    {
        return true;
    }

    /**
     * @return void
     */
    public function setUp(): void {}

    /**
     * @return void
     */
    public function tearDown(): void {}
}
