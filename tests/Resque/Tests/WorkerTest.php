<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * \Resque\Worker tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class WorkerTest extends TestCase
{
    public function testPausedWorkerDoesNotPickUpJobs()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->pauseProcessing();
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        $worker->work(0);
        $worker->work(0);
        static::assertEquals(0, \Resque\Stat::get('processed'));
    }

    public function testResumedWorkerPicksUpJobs()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->pauseProcessing();
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        $worker->work(0);
        static::assertEquals(0, \Resque\Stat::get('processed'));
        $worker->unPauseProcessing();
        $worker->work(0);
        static::assertEquals(1, \Resque\Stat::get('processed'));
    }

    public function testWorkerCanWorkOverMultipleQueues()
    {
        $worker = new \Resque\Worker([
            'queue1',
            'queue2',
        ]);
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();
        \Resque\Resque::enqueue('queue1', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('queue2', '\Resque\Test\TestJob_2');

        $job = $worker->reserve();
        static::assertEquals('queue1', $job->queue);

        $job = $worker->reserve();
        static::assertEquals('queue2', $job->queue);
    }

    public function testWorkerWorksQueuesInSpecifiedOrder()
    {
        $worker = new \Resque\Worker([
            'high',
            'medium',
            'low',
        ]);
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        // Queue the jobs in a different order
        \Resque\Resque::enqueue('low', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('high', '\Resque\Test\TestJob_2');
        \Resque\Resque::enqueue('medium', '\Resque\Test\TestJob_3');

        // Now check we get the jobs back in the right order
        $job = $worker->reserve();
        static::assertEquals('high', $job->queue);

        $job = $worker->reserve();
        static::assertEquals('medium', $job->queue);

        $job = $worker->reserve();
        static::assertEquals('low', $job->queue);
    }

    public function testWildcardQueueWorkerWorksAllQueues()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        \Resque\Resque::enqueue('queue1', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('queue2', '\Resque\Test\TestJob_2');

        $job = $worker->reserve();
        static::assertEquals('queue1', $job->queue);

        $job = $worker->reserve();
        static::assertEquals('queue2', $job->queue);
    }

    public function testWorkerDoesNotWorkOnUnknownQueues()
    {
        $worker = new \Resque\Worker('queue1');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();
        \Resque\Resque::enqueue('queue2', '\Resque\Test\TestJob');

        static::assertFalse($worker->reserve());
    }

    public function testWorkerClearsItsStatusWhenNotWorking()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $job = $worker->reserve();
        $worker->workingOn($job);
        $worker->doneWorking();
        static::assertEquals([], $worker->job());
    }

    public function testWorkerRecordsWhatItIsWorkingOn()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $payload = [
            'class' => '\Resque\Test\TestJob',
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $worker->workingOn($job);

        $job = $worker->job();
        static::assertEquals('jobs', $job['queue']);
        if (!array_key_exists('run_at', $job)) {
            static::fail('Job does not have run_at time');
        }
        static::assertEquals($payload, $job['payload']);
    }

    public function testWorkerErasesItsStatsWhenShutdown()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        \Resque\Resque::enqueue('jobs', '\Resque\Test\InvalidJob');

        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->work(0);
        $worker->work(0);

        // Allow time for async unlink to work
        sleep(2);

        static::assertEquals(0, $worker->getStat('processed'));
        static::assertEquals(0, $worker->getStat('failed'));
    }

    public function testWorkerFailsUncompletedJobsOnExit()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $payload = [
            'class' => '\Resque\Test\TestJob',
        ];
        $job = new \Resque\Job\Job('jobs', $payload);

        $worker->workingOn($job);
        $worker->unregisterWorker();

        static::assertEquals(1, \Resque\Stat::get('failed'));
    }

    public function testBlockingListPop()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob_2');

        $i = 1;
        while (true) {
            $job = $worker->reserve(true, 2);
            if (!$job) {
                break;
            }

            static::assertEquals('\Resque\Test\TestJob_' . $i, $job->payload['class']);

            if ($i === 2) {
                break;
            }

            $i++;
        }

        static::assertEquals(2, $i);
    }

    public function testWildcardQueuesAreCached()
    {
        \Resque\Resque::push('queue1', ['class' => '\Resque\Test\TestJob']);

        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());

        static::assertEquals(['queue1'], $worker->queues());

        // A queue created within the cache TTL is not visible until the cache refreshes.
        \Resque\Resque::push('queue2', ['class' => '\Resque\Test\TestJob']);
        static::assertEquals(['queue1'], $worker->queues());
    }

    public function testQueuesWithoutFetchDoesNotExpandWildcard()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());

        static::assertEquals(['*'], $worker->queues(false));
    }

    public function testJobReturnsEmptyArrayWhenIdle()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        static::assertEquals([], $worker->job());
    }

    public function testGetStatReturnsWorkerScopedProcessedCount()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');

        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $job = $worker->reserve();
        $worker->workingOn($job);
        $worker->doneWorking();

        static::assertEquals(1, $worker->getStat('processed'));
    }
}
