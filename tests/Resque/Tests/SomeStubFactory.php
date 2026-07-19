<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture job factory returning a SomeJobClass instance.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class SomeStubFactory implements \Resque\Job\FactoryInterface
{
    public static $called = false;
    public $args = false;
    public $queue;
    public $job;

    /**
     * @param $className
     * @param $args
     * @param $queue
     *
     * @return \Resque\Job\JobInterface
     */
    public function create($className, $args, $queue)
    {
        return new SomeJobClass();
    }
}
