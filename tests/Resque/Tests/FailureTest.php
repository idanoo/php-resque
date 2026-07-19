<?php

declare(strict_types=1);

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
        static::assertEquals('\\Resque\\Failure\\ResqueFailureRedis', \Resque\Failure\Failure::getBackend());
    }

    public function testCustomBackendReceivesFailure()
    {
        \Resque\Failure\Failure::setBackend('\\Resque\\Test\\TestFailureBackend');

        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());

        $exception = new \Exception('Test error');
        $payload = ['class' => '\Resque\Test\TestJob', 'args' => [[]]];
        \Resque\Failure\Failure::create($payload, $exception, $worker, 'jobs');

        static::assertEquals($payload, TestFailureBackend::$payload);
        static::assertSame($exception, TestFailureBackend::$exception);
        static::assertEquals('jobs', TestFailureBackend::$queue);
        static::assertEquals((string) $worker, (string) TestFailureBackend::$worker);
    }

    public function testRedisBackendPushesFailureOntoFailedList()
    {
        $worker = new \Resque\Worker('jobs');
        $worker->setLogger(new \Resque\Log());

        $exception = new \Exception('Something broke');
        $payload = ['class' => '\Resque\Test\TestJob', 'args' => [[]], 'id' => 'abc123'];

        new \Resque\Failure\ResqueFailureRedis($payload, $exception, $worker, 'jobs');

        static::assertEquals(1, $this->redis->llen('resque:failed'));

        $data = json_decode($this->redis->lindex('resque:failed', 0), associative: true);
        static::assertEquals($payload, $data['payload']);
        static::assertEquals('Exception', $data['exception']);
        static::assertEquals('Something broke', $data['error']);
        static::assertEquals('jobs', $data['queue']);
        static::assertEquals((string) $worker, $data['worker']);
        static::assertNotEmpty($data['failed_at']);
        static::assertIsArray($data['backtrace']);
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

        static::assertEquals(1, \Resque\Stat::get('failed'));
        static::assertEquals(1, \Resque\Stat::get('failed:' . (string) $worker));
        static::assertEquals(1, $this->redis->llen('resque:failed'));
    }
}
