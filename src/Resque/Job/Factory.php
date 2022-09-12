<?php

namespace Resque\Job;

/**
 * Job Factory!
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Factory implements FactoryInterface
{
    /**
     * @param $className
     * @param array $args
     * @param $queue
     *
     * @return \Resque\Job\JobInterface
     *
     * @throws \Resque\Exception
     */
    public function create($className, $args, $queue)
    {
        if (!class_exists($className)) {
            throw new \Resque\Exception(
                'Could not find job class ' . $className . '.'
            );
        }

        if (!method_exists($className, 'perform')) {
            throw new \Resque\Exception(
                'Job class ' . $className . ' does not contain a perform() method.'
            );
        }

        $instance = new $className();
        $instance->args = $args;
        $instance->queue = $queue;

        return $instance;
    }
}
