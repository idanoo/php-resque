<?php

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
    public function testWorkerRegistersInList()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        // Make sure the worker is in the list
        $this->assertTrue((bool)$this->redis->sismember('resque:workers', (string)$worker));
    }

    public function testGetAllWorkers()
    {
        $num = 3;
        // Register a few workers
        for ($i = 0; $i < $num; ++$i) {
            $worker = new \Resque\Worker('queue_' . $i);
            $worker->setLogger(new \Resque\Log());
            $worker->registerWorker();
        }

        // Now try to get them
        $this->assertEquals($num, count(\Resque\Worker::all()));
    }

    public function testGetWorkerById()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $newWorker = \Resque\Worker::find((string)$worker);
        $this->assertEquals((string)$worker, (string)$newWorker);
    }

    public function testInvalidWorkerDoesNotExist()
    {
        $this->assertFalse(\Resque\Worker::exists('blah'));
    }

    public function testWorkerCanUnregister()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();
        $worker->unregisterWorker();

        $this->assertFalse(\Resque\Worker::exists((string)$worker));
        $this->assertEquals([], \Resque\Worker::all());
        $this->assertEquals([], $this->redis->smembers('resque:workers'));
    }

    public function testPausedWorkerDoesNotPickUpJobs()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->pauseProcessing();
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        $worker->work(0);
        $worker->work(0);
        $this->assertEquals(0, \Resque\Stat::get('processed'));
    }

    public function testResumedWorkerPicksUpJobs()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->pauseProcessing();
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        $worker->work(0);
        $this->assertEquals(0, \Resque\Stat::get('processed'));
        $worker->unPauseProcessing();
        $worker->work(0);
        $this->assertEquals(1, \Resque\Stat::get('processed'));
    }

    public function testWorkerCanWorkOverMultipleQueues()
    {
        $worker = new \Resque\Worker([
            'queue1',
            'queue2'
        ]);
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();
        \Resque\Resque::enqueue('queue1', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('queue2', '\Resque\Test\TestJob_2');

        $job = $worker->reserve();
        $this->assertEquals('queue1', $job->queue);

        $job = $worker->reserve();
        $this->assertEquals('queue2', $job->queue);
    }

    public function testWorkerWorksQueuesInSpecifiedOrder()
    {
        $worker = new \Resque\Worker([
            'high',
            'medium',
            'low'
        ]);
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        // Queue the jobs in a different order
        \Resque\Resque::enqueue('low', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('high', '\Resque\Test\TestJob_2');
        \Resque\Resque::enqueue('medium', '\Resque\Test\TestJob_3');

        // Now check we get the jobs back in the right order
        $job = $worker->reserve();
        $this->assertEquals('high', $job->queue);

        $job = $worker->reserve();
        $this->assertEquals('medium', $job->queue);

        $job = $worker->reserve();
        $this->assertEquals('low', $job->queue);
    }

    public function testWildcardQueueWorkerWorksAllQueues()
    {
        $worker = new \Resque\Worker('*');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        \Resque\Resque::enqueue('queue1', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('queue2', '\Resque\Test\TestJob_2');

        $job = $worker->reserve();
        $this->assertEquals('queue1', $job->queue);

        $job = $worker->reserve();
        $this->assertEquals('queue2', $job->queue);
    }

    public function testWorkerDoesNotWorkOnUnknownQueues()
    {
        $worker = new \Resque\Worker('queue1');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();
        \Resque\Resque::enqueue('queue2', '\Resque\Test\TestJob');

        $this->assertFalse($worker->reserve());
    }

    public function testWorkerClearsItsStatusWhenNotWorking()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $job = $worker->reserve();
        $worker->workingOn($job);
        $worker->doneWorking();
        $this->assertEquals([], $worker->job());
    }

    public function testWorkerRecordsWhatItIsWorkingOn()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $payload = [
            'class' => '\Resque\Test\TestJob'
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $worker->workingOn($job);

        $job = $worker->job();
        $this->assertEquals('jobs', $job['queue']);
        if (!isset($job['run_at'])) {
            $this->fail('Job does not have run_at time');
        }
        $this->assertEquals($payload, $job['payload']);
    }

    public function testWorkerErasesItsStatsWhenShutdown()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        \Resque\Resque::enqueue('jobs', '\Resque\Test\InvalidJob');

        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->work(0);
        $worker->work(0);

        $this->assertEquals(0, $worker->getStat('processed'));
        $this->assertEquals(0, $worker->getStat('failed'));
    }

    public function testWorkerCleansUpDeadWorkersOnStartup()
    {
        // Register a good worker
        $goodWorker = new \Resque\Worker('jobs');
        $goodWorker->setLogger(new \Resque\Log());
        $goodWorker->registerWorker();
        $workerId = explode(':', $goodWorker);

        // Register some bad workers
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->setId($workerId[0] . ':1:jobs');
        $worker->registerWorker();

        $worker = new \Resque\Worker(['high', 'low']);
        $worker->setLogger(new \Resque\Log());
        $worker->setId($workerId[0] . ':2:high,low');
        $worker->registerWorker();

        $this->assertEquals(3, count(\Resque\Worker::all()));

        $goodWorker->pruneDeadWorkers();

        // There should only be $goodWorker left now
        $this->assertEquals(1, count(\Resque\Worker::all()));
    }

    public function testDeadWorkerCleanUpDoesNotCleanUnknownWorkers()
    {
        // Register a bad worker on this machine
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $workerId = explode(':', $worker);
        $worker->setId($workerId[0] . ':1:jobs');
        $worker->registerWorker();

        // Register some other false workers
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->setId('my.other.host:1:jobs');
        $worker->registerWorker();

        $this->assertEquals(2, count(\Resque\Worker::all()));

        $worker->pruneDeadWorkers();

        // my.other.host should be left
        $workers = \Resque\Worker::all();
        $this->assertEquals(1, count($workers));
        $this->assertEquals((string)$worker, (string)$workers[0]);
    }

    public function testWorkerFailsUncompletedJobsOnExit()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $payload = [
            'class' => '\Resque\Test\TestJob'
        ];
        $job = new \Resque\Job\Job('jobs', $payload);

        $worker->workingOn($job);
        $worker->unregisterWorker();

        $this->assertEquals(1, \Resque\Stat::get('failed'));
    }

    public function testBlockingListPop()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob_1');
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob_2');

        $i = 1;
        while ($job = $worker->reserve(true, 2)) {
            $this->assertEquals('\Resque\Test\TestJob_' . $i, $job->payload['class']);

            if ($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }
}