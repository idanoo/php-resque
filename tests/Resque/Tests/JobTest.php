<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * ResqueJob tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class JobTest extends TestCase
{
    protected \Resque\Worker $worker;

    public function setUp(): void
    {
        parent::setUp();

        // Register a worker to test with
        $this->worker = new \Resque\Worker('jobs');
        $this->worker->setLogger(new \Resque\Log());
        $this->worker->registerWorker();
    }

    public function testJobCanBeQueued()
    {
        static::assertTrue((bool) \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob'));
    }

    public function testQeueuedJobCanBeReserved()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');

        $job = \Resque\Job\Job::reserve('jobs');
        if (is_null($job)) {
            static::fail('Job could not be reserved.');
        }
        static::assertEquals('jobs', $job->queue);
        static::assertEquals('\Resque\Test\TestJob', $job->payload['class']);
    }

    public function testObjectArgumentsCannotBePassedToJob()
    {
        $this->expectException(\InvalidArgumentException::class);

        $args = new \stdClass();
        $args->test = 'somevalue';
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', $args);
    }

    public function testQueuedJobReturnsExactSamePassedInArguments()
    {
        $args = [
            'int' => 123,
            'numArray' => [
                1,
                2,
            ],
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', $args);
        $job = \Resque\Job\Job::reserve('jobs');

        static::assertEquals($args, $job->getArguments());
    }

    public function testAfterJobIsReservedItIsRemoved()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');
        \Resque\Job\Job::reserve('jobs');
        static::assertNull(\Resque\Job\Job::reserve('jobs'));
    }

    public function testRecreatedJobMatchesExistingJob()
    {
        $args = [
            'int' => 123,
            'numArray' => [
                1,
                2,
            ],
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];

        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', $args);
        $job = \Resque\Job\Job::reserve('jobs');

        // Now recreate it
        $job->recreate();

        $newJob = \Resque\Job\Job::reserve('jobs');
        static::assertEquals($job->payload['class'], $newJob->payload['class']);
        static::assertEquals($job->getArguments(), $newJob->getArguments());
    }

    public function testFailedJobExceptionsAreCaught()
    {
        $payload = [
            'class' => '\Resque\Test\FailingJob',
            'args' => null,
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $job->worker = $this->worker;

        $this->worker->perform($job);

        static::assertEquals(1, \Resque\Stat::get('failed'));
        static::assertEquals(1, \Resque\Stat::get('failed:' . $this->worker));
    }

    public function testJobWithoutPerformMethodThrowsException()
    {
        $this->expectException(\Resque\Exception::class);
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob_Without_Perform_Method');
        $job = $this->worker->reserve();
        $job->worker = $this->worker;
        $job->perform();
    }

    public function testInvalidJobThrowsException()
    {
        $this->expectException(\Resque\Exception::class);
        \Resque\Resque::enqueue('jobs', '\Resque\Test\InvalidJob');
        $job = $this->worker->reserve();
        $job->worker = $this->worker;
        $job->perform();
    }

    public function testJobWithSetUpCallbackFiresSetUp()
    {
        $payload = [
            'class' => '\Resque\Test\TestJobWithSetUp',
            'args' => [
                'somevar',
                'somevar2',
            ],
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $job->perform();

        static::assertTrue(TestJobWithSetUp::$called);
    }

    public function testJobWithTearDownCallbackFiresTearDown()
    {
        $payload = [
            'class' => '\Resque\Test\TestJobWithTearDown',
            'args' => [
                'somevar',
                'somevar2',
            ],
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $job->perform();

        static::assertTrue(TestJobWithTearDown::$called);
    }

    public function testNamespaceNaming()
    {
        $fixture = [
            ['test' => 'more:than:one:with:', 'assertValue' => 'more:than:one:with:'],
            ['test' => 'more:than:one:without', 'assertValue' => 'more:than:one:without:'],
            ['test' => 'resque', 'assertValue' => 'resque:'],
            ['test' => 'resque:', 'assertValue' => 'resque:'],
        ];

        foreach ($fixture as $item) {
            \Resque\Redis::prefix($item['test']);
            static::assertEquals(\Resque\Redis::getPrefix(), $item['assertValue']);
        }
    }

    public function testJobWithNamespace()
    {
        \Resque\Redis::prefix('php');
        $queue = 'jobs';
        $payload = ['another_value'];
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobWithTearDown', $payload);

        static::assertEquals(\Resque\Resque::queues(), ['jobs']);
        static::assertEquals(\Resque\Resque::size($queue), 1);

        \Resque\Redis::prefix('resque');
        static::assertEquals(\Resque\Resque::size($queue), 0);
    }

    public function testDequeueAll()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue');
        static::assertEquals(\Resque\Resque::size($queue), 2);
        static::assertEquals(\Resque\Resque::dequeue($queue), 2);
        static::assertEquals(\Resque\Resque::size($queue), 0);
    }

    public function testDequeueMakeSureNotDeleteOthers()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue');
        $other_queue = 'other_jobs';
        \Resque\Resque::enqueue($other_queue, '\Resque\Test\TestJobDequeue');
        \Resque\Resque::enqueue($other_queue, '\Resque\Test\TestJobDequeue');
        static::assertEquals(\Resque\Resque::size($queue), 2);
        static::assertEquals(\Resque\Resque::size($other_queue), 2);
        static::assertEquals(\Resque\Resque::dequeue($queue), 2);
        static::assertEquals(\Resque\Resque::size($queue), 0);
        static::assertEquals(\Resque\Resque::size($other_queue), 2);
    }

    public function testDequeueSpecificItem()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue1');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue2');
        static::assertEquals(\Resque\Resque::size($queue), 2);
        $test = ['\Resque\Test\TestJobDequeue2'];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 1);
        static::assertEquals(\Resque\Resque::size($queue), 1);
    }

    public function testDequeueSpecificMultipleItems()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue1');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue2');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue3');
        static::assertEquals(\Resque\Resque::size($queue), 3);
        $test = ['\Resque\Test\TestJob_Dequeue2', '\Resque\Test\TestJob_Dequeue3'];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 2);
        static::assertEquals(\Resque\Resque::size($queue), 1);
    }

    public function testDequeueNonExistingItem()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue1');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue2');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue3');
        static::assertEquals(\Resque\Resque::size($queue), 3);
        $test = ['\Resque\Test\TestJob_Dequeue4'];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 0);
        static::assertEquals(\Resque\Resque::size($queue), 3);
    }

    public function testDequeueNonExistingItem2()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue1');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue2');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue3');
        static::assertEquals(\Resque\Resque::size($queue), 3);
        $test = ['\Resque\Test\TestJob_Dequeue4', '\Resque\Test\TestJob_Dequeue1'];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 1);
        static::assertEquals(\Resque\Resque::size($queue), 2);
    }

    public function testDequeueItemID()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue');
        $qid = \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue');
        static::assertEquals(\Resque\Resque::size($queue), 2);
        $test = ['\Resque\Test\TestJob_Dequeue' => $qid];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 1);
        static::assertEquals(\Resque\Resque::size($queue), 1);
    }

    public function testDequeueWrongItemID()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue');
        $qid = \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue');
        static::assertEquals(\Resque\Resque::size($queue), 2);
        // qid right but class name is wrong
        $test = ['\Resque\Test\TestJob_Dequeue1' => $qid];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 0);
        static::assertEquals(\Resque\Resque::size($queue), 2);
    }

    public function testDequeueWrongItemID2()
    {
        $queue = 'jobs';
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJob_Dequeue');
        static::assertEquals(\Resque\Resque::size($queue), 2);
        $test = ['\Resque\Test\TestJob_Dequeue' => 'r4nD0mH4sh3dId'];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 0);
        static::assertEquals(\Resque\Resque::size($queue), 2);
    }

    public function testDequeueItemWithArg()
    {
        $queue = 'jobs';
        $arg = ['foo' => 1, 'bar' => 2];
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue9');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue9', $arg);
        static::assertEquals(\Resque\Resque::size($queue), 2);
        $test = ['\Resque\Test\TestJobDequeue9' => $arg];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 1);

        // $this->assertEquals(\Resque\Resque::size($queue), 1);
    }

    public function testDequeueSeveralItemsWithArgs()
    {
        // GIVEN
        $queue = 'jobs';
        $args = ['foo' => 1, 'bar' => 10];
        $removeArgs = ['foo' => 1, 'bar' => 2];
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue9', $args);
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue9', $removeArgs);
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue9', $removeArgs);
        static::assertEquals(\Resque\Resque::size($queue), 3, 'Failed to add 3 items.');

        // WHEN
        $test = ['\Resque\Test\TestJobDequeue9' => $removeArgs];
        $removedItems = \Resque\Resque::dequeue($queue, $test);

        // THEN
        static::assertEquals($removedItems, 2);
        static::assertEquals(\Resque\Resque::size($queue), 1);
        $item = \Resque\Resque::pop($queue);
        static::assertIsArray($item['args']);
        static::assertEquals(10, $item['args'][0]['bar'], 'Wrong items were dequeued from queue!');
    }

    public function testDequeueItemWithUnorderedArg()
    {
        $queue = 'jobs';
        $arg = ['foo' => 1, 'bar' => 2];
        $arg2 = ['bar' => 2, 'foo' => 1];
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue', $arg);
        static::assertEquals(\Resque\Resque::size($queue), 2);
        $test = ['\Resque\Test\TestJobDequeue' => $arg2];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 1);
        static::assertEquals(\Resque\Resque::size($queue), 1);
    }

    public function testDequeueItemWithiWrongArg()
    {
        $queue = 'jobs';
        $arg = ['foo' => 1, 'bar' => 2];
        $arg2 = ['foo' => 2, 'bar' => 3];
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue');
        \Resque\Resque::enqueue($queue, '\Resque\Test\TestJobDequeue', $arg);
        static::assertEquals(\Resque\Resque::size($queue), 2);
        $test = ['\Resque\Test\TestJobDequeue' => $arg2];
        static::assertEquals(\Resque\Resque::dequeue($queue, $test), 0);
        static::assertEquals(\Resque\Resque::size($queue), 2);
    }

    public function testUseDefaultFactoryToGetJobInstance()
    {
        $payload = [
            'class' => '\Resque\Test\SomeJobClass',
            'args' => null,
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $instance = $job->getInstance();
        static::assertInstanceOf('\Resque\Test\SomeJobClass', $instance);
    }

    public function testUseFactoryToGetJobInstance()
    {
        $payload = [
            'class' => 'SomeJobClass',
            'args' => [[]],
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $factory = new SomeStubFactory();
        $job->setJobFactory($factory);
        $instance = $job->getInstance();
        static::assertInstanceOf('\Resque\Job\JobInterface', $instance);
    }

    public function testToStringIncludesQueueClassIdAndArgs()
    {
        $job = new \Resque\Job\Job('jobs', [
            'class' => '\Resque\Test\TestJob',
            'args' => [['foo' => 'bar']],
            'id' => 'abc123',
        ]);

        $string = (string) $job;
        static::assertStringContainsString('Job{jobs}', $string);
        static::assertStringContainsString('ID: abc123', $string);
        static::assertStringContainsString('\Resque\Test\TestJob', $string);
        static::assertStringContainsString('foo', $string);
    }

    public function testToStringIsMinimalWithoutIdOrArgs()
    {
        $job = new \Resque\Job\Job('jobs', ['class' => '\Resque\Test\TestJob']);
        static::assertEquals('(Job{jobs} | \Resque\Test\TestJob)', (string) $job);
    }

    public function testReserveBlockingReturnsNullWhenQueueEmpty()
    {
        static::assertNull(\Resque\Job\Job::reserveBlocking(['jobs'], 1));
    }

    public function testReserveBlockingReturnsQueuedJob()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob');

        $job = \Resque\Job\Job::reserveBlocking(['jobs'], 2);
        static::assertNotNull($job);
        static::assertEquals('jobs', $job->queue);
        static::assertEquals('\Resque\Test\TestJob', $job->payload['class']);
    }

    public function testRecreatedJobHasNewId()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', ['foo' => 'bar']);
        $job = \Resque\Job\Job::reserve('jobs');
        $originalId = $job->payload['id'];

        $newId = $job->recreate();
        static::assertNotEquals($originalId, $newId);

        $newJob = \Resque\Job\Job::reserve('jobs');
        static::assertEquals($job->payload['class'], $newJob->payload['class']);
        static::assertEquals($job->getArguments(), $newJob->getArguments());
    }
}
