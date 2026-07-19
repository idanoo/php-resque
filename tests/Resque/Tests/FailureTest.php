<?php

namespace Resque\Test;

/**
 * \Resque\Failure tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class FailureTest extends TestCase
{
    public function testDefaultBackendIsRedis()
    {
        $this->assertEquals(
            '\\Resque\\Failure\\ResqueFailureRedis',
            \Resque\Failure\Failure::getBackend()
        );
    }

    public function testCustomBackendReceivesFailure()
    {
        \Resque\Failure\Failure::setBackend('\\Resque\\Test\\TestFailureBackend');

        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());

        $exception = new \Exception('Test error');
        $payload = ['class' => '\Resque\Test\TestJob', 'args' => [[]]];
        \Resque\Failure\Failure::create($payload, $exception, $worker, 'jobs');

        $this->assertEquals($payload, TestFailureBackend::$payload);
        $this->assertSame($exception, TestFailureBackend::$exception);
        $this->assertEquals('jobs', TestFailureBackend::$queue);
        $this->assertEquals((string)$worker, (string)TestFailureBackend::$worker);
    }

    public function testRedisBackendPushesFailureOntoFailedList()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());

        $exception = new \Exception('Something broke');
        $payload = ['class' => '\Resque\Test\TestJob', 'args' => [[]], 'id' => 'abc123'];

        new \Resque\Failure\ResqueFailureRedis($payload, $exception, $worker, 'jobs');

        $this->assertEquals(1, $this->redis->llen('resque:failed'));

        $data = json_decode($this->redis->lindex('resque:failed', 0), true);
        $this->assertEquals($payload, $data['payload']);
        $this->assertEquals('Exception', $data['exception']);
        $this->assertEquals('Something broke', $data['error']);
        $this->assertEquals('jobs', $data['queue']);
        $this->assertEquals((string)$worker, $data['worker']);
        $this->assertNotEmpty($data['failed_at']);
        $this->assertIsArray($data['backtrace']);
    }

    public function testJobFailRecordsFailureAndIncrementsStats()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());
        $worker->registerWorker();

        $job = new \Resque\Job\Job('jobs', [
            'class' => '\Resque\Test\FailingJob',
            'args' => [[]],
            'id' => 'job123',
        ]);
        $job->worker = $worker;

        $job->fail(new \Exception('boom'));

        $this->assertEquals(1, \Resque\Stat::get('failed'));
        $this->assertEquals(1, \Resque\Stat::get('failed:' . (string)$worker));
        $this->assertEquals(1, $this->redis->llen('resque:failed'));
    }
}
