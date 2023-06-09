<?php

namespace Resque\Failure;

/**
 * Failed Resque job.
 *
 * @package        Resque/Failure
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Failure
{
    /**
     * @var string Class name representing the backend to pass failed jobs off to.
     */
    private static $backend;

    /**
     * Create a new failed job on the backend.
     *
     * @param array $payload The contents of the job that has just failed.
     * @param \Exception $exception The exception generated when the job failed to run.
     * @param \Resque\Worker $worker Instance of Resque_Worker that was running this job when it failed.
     * @param string $queue The name of the queue that this job was fetched from.
     */
    public static function create(
        $payload,
        \Exception $exception,
        \Resque\Worker $worker,
        $queue
    ) {
        $backend = self::getBackend();
        new $backend($payload, $exception, $worker, $queue);
    }

    /**
     * Return an instance of the backend for saving job failures.
     *
     * @return string
     */
    public static function getBackend()
    {
        if (is_null(self::$backend)) {
            self::$backend = '\\Resque\\Failure\\ResqueFailureRedis';
        }

        return self::$backend;
    }

    /**
     * Set the backend to use for raised job failures. The supplied backend
     * should be the name of a class to be instantiated when a job fails.
     * It is your responsibility to have the backend class loaded (or autoloaded)
     *
     * @param string $backend The class name of the backend to pipe failures to.
     *
     * @return void
     */
    public static function setBackend(string $backend): void
    {
        self::$backend = $backend;
    }
}
