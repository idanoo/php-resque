<?php

namespace Resque\Test;

/**
 * \Resque\Resque core queue API tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class ResqueTest extends TestCase
{
    public function testPushInsertsJobOntoQueue()
    {
        $this->assertTrue(\Resque\Resque::push('jobs', ['class' => 'TestJob']));
        $this->assertEquals(1, \Resque\Resque::size('jobs'));
    }

    public function testPoppedJobMatchesPushedJob()
    {
        $item = ['class' => 'TestJob', 'args' => [['foo' => 'bar']]];
        \Resque\Resque::push('jobs', $item);

        $this->assertEquals($item, \Resque\Resque::pop('jobs'));
    }

    public function testPopReturnsFalseOnEmptyQueue()
    {
        $this->assertFalse(\Resque\Resque::pop('jobs'));
    }

    public function testSizeOfEmptyQueueIsZero()
    {
        $this->assertEquals(0, \Resque\Resque::size('nonexistent'));
    }

    public function testSizeReflectsNumberOfQueuedJobs()
    {
        \Resque\Resque::push('jobs', ['class' => 'TestJob']);
        \Resque\Resque::push('jobs', ['class' => 'TestJob']);
        $this->assertEquals(2, \Resque\Resque::size('jobs'));
    }

    public function testQueuesReturnsAllKnownQueues()
    {
        \Resque\Resque::push('queue1', ['class' => 'TestJob']);
        \Resque\Resque::push('queue2', ['class' => 'TestJob']);

        $queues = \Resque\Resque::queues();
        $this->assertContains('queue1', $queues);
        $this->assertContains('queue2', $queues);
    }

    public function testQueuesReturnsEmptyArrayWhenNoneExist()
    {
        $this->assertEquals([], \Resque\Resque::queues());
    }

    public function testRemoveQueueDeletesQueueAndReturnsCount()
    {
        \Resque\Resque::push('jobs', ['class' => 'TestJob']);
        \Resque\Resque::push('jobs', ['class' => 'TestJob']);

        $this->assertEquals(2, \Resque\Resque::removeQueue('jobs'));
        $this->assertEquals(0, \Resque\Resque::size('jobs'));
        $this->assertNotContains('jobs', \Resque\Resque::queues());
    }

    public function testGenerateJobIdReturnsHexStringOfExpectedLength()
    {
        $id = \Resque\Resque::generateJobId();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
    }

    public function testGenerateJobIdReturnsUniqueValues()
    {
        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[\Resque\Resque::generateJobId()] = true;
        }

        // No collisions across 1000 generated IDs.
        $this->assertCount(1000, $ids);
    }
}
