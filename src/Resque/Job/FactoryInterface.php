<?php

namespace Resque\Job;

/**
 * Job Interface
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
interface FactoryInterface
{
    /**
     * @param $className
     * @param array $args
     * @param $queue
     *
     * @return \Resque\Job\JobInterface
     */
    public function create($className, $args, $queue);
}
