<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture job that always throws to exercise failure handling.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class FailingJob
{
    public static $called = false;
    public $args = false;
    public $queue;
    public $job;

    public function perform()
    {
        throw new FailingJobException('Message!');
    }
}
