<?php

namespace Resque\Test;

/**
 * \Resque\Event tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class EventTest extends TestCase
{
    private $callbacksHit = [];
    private $worker;

    public function setUp(): void
    {
        TestJob::$called = false;

        // Register a worker to test with
        $this->worker = new \Resque\Worker('jobs');
        $this->worker->setLogger(new \Resque\Log());
        $this->worker->registerWorker();
    }

    public function tearDown(): void
    {
        \Resque\Event::clearListeners();
        $this->callbacksHit = [];
    }

    public function getEventTestJob()
    {
        $payload = [
            'class' => '\Resque\Test\TestJob',
            'args' => [
                ['somevar'],
            ],
        ];
        $job = new \Resque\Job\Job('jobs', $payload);
        $job->worker = $this->worker;
        return $job;
    }

    public function eventCallbackProvider()
    {
        return [
            ['beforePerform', 'beforePerformEventCallback'],
            ['afterPerform', 'afterPerformEventCallback'],
            ['afterFork', 'afterForkEventCallback'],
        ];
    }

    /**
     * @dataProvider eventCallbackProvider
     * @param $event
     * @param $callback
     */
    public function testEventCallbacksFire($event, $callback)
    {
        \Resque\Event::listen($event, [$this, $callback]);

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testBeforeForkEventCallbackFires()
    {
        $event = 'beforeFork';
        $callback = 'beforeForkEventCallback';

        \Resque\Event::listen($event, [$this, $callback]);
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', [
            'somevar'
        ]);
        $this->getEventTestJob();
        $this->worker->work(0);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testBeforeEnqueueEventCallbackFires()
    {
        $event = 'beforeEnqueue';
        $callback = 'beforeEnqueueEventCallback';

        \Resque\Event::listen($event, [$this, $callback]);
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', [
            'somevar'
        ]);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testBeforePerformEventCanStopWork()
    {
        $callback = 'beforePerformEventDontPerformCallback';
        \Resque\Event::listen('beforePerform', [$this, $callback]);

        $job = $this->getEventTestJob();

        $this->assertFalse($job->perform());
        $this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
        $this->assertFalse(TestJob::$called, 'Job was still performed though Resque_Job_DontPerform was thrown');
    }

    public function testBeforeEnqueueEventStopsJobCreation()
    {
        $callback = 'beforeEnqueueEventDontCreateCallback';
        \Resque\Event::listen('beforeEnqueue', [$this, $callback]);
        \Resque\Event::listen('afterEnqueue', [$this, 'afterEnqueueEventCallback']);

        $result = \Resque\Resque::enqueue('jobs', '\Resque\Test\TestClass');
        $this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
        $this->assertNotContains('afterEnqueueEventCallback', $this->callbacksHit, 'afterEnqueue was still called, even though it should not have been');
        $this->assertFalse($result);
    }

    public function testAfterEnqueueEventCallbackFires()
    {
        $callback = 'afterEnqueueEventCallback';
        $event = 'afterEnqueue';

        \Resque\Event::listen($event, [$this, $callback]);
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', [
            'somevar'
        ]);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testStopListeningRemovesListener()
    {
        $callback = 'beforePerformEventCallback';
        $event = 'beforePerform';

        \Resque\Event::listen($event, [$this, $callback]);
        \Resque\Event::stopListening($event, [$this, $callback]);

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertNotContains($callback, $this->callbacksHit,
            $event . ' callback (' . $callback . ') was called though Resque_Event::stopListening was called'
        );
    }

    public function beforePerformEventDontPerformCallback()
    {
        $this->callbacksHit[] = __FUNCTION__;
        throw new \Resque\Job\DontPerform();
    }

    public function beforeEnqueueEventDontCreateCallback()
    {
        $this->callbacksHit[] = __FUNCTION__;
        throw new \Resque\Job\DontCreate();
    }

    public function assertValidEventCallback($function, $job)
    {
        $this->callbacksHit[] = $function;
        if (!$job instanceof \Resque\Job\Job) {
            $this->fail('Callback job argument is not an instance of \Resque\Job\Job');
        }
        $args = $job->getArguments();
        $this->assertEquals($args[0], 'somevar');
    }

    public function afterEnqueueEventCallback($class, $args)
    {
        $this->callbacksHit[] = __FUNCTION__;
        $this->assertEquals('\Resque\Test\TestJob', $class);
        $this->assertEquals([
            'somevar',
        ], $args);
    }

    public function beforeEnqueueEventCallback($job)
    {
        $this->callbacksHit[] = __FUNCTION__;
    }

    public function beforePerformEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function afterPerformEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function beforeForkEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function afterForkEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }
}
