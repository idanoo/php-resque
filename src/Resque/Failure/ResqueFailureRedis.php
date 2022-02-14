<?php

namespace Resque\Failure;

/**
 * Redis backend for storing failed Resque jobs.
 *
 * @package        Resque\Failure
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class ResqueFailureRedis implements ResqueFailureInterface
{
    /**
     * Initialize a failed job class and save it (where appropriate).
     *
     * @param object $payload Object containing details of the failed job.
     * @param object $exception Instance of the exception that was thrown by the failed job.
     * @param object $worker Instance of Resque_Worker that received the job.
     * @param string $queue The name of the queue the job was fetched from.
     * @throws \Resque\RedisException
     */
    public function __construct($payload, $exception, $worker, $queue)
    {
        $data = new \stdClass();
        $data->failed_at = date('D M d H:i:s T Y');
        $data->payload = $payload;
        $data->exception = get_class($exception);
        $data->error = $exception->getMessage();
        $data->backtrace = explode("\n", $exception->getTraceAsString());
        $data->worker = (string)$worker;
        $data->queue = $queue;
        $data = json_encode($data);
        \Resque\Resque::redis()->rpush('failed', $data);
    }
}
