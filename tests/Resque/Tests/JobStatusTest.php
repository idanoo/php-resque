<?php

namespace Resque\Test;

/**
 * \Resque\Job\Status tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class JobStatusTest extends TestCase
{
    /**
     * @var \Resque\Worker
     */
    protected $worker;

    public function setUp(): void
    {
        parent::setUp();

        // Register a worker to test with
        $this->worker = new \Resque\Worker('jobs');
        $this->worker->setLogger(new \Resque\Log());
    }

    public function testJobStatusCanBeTracked()
    {
        $token = \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', null, true);
        $status = new \Resque\Job\Status($token);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', null, true);
        $job = \Resque\Job\Job::reserve('jobs');
        $this->assertEquals(\Resque\Job\Status::STATUS_WAITING, $job->getStatus());
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token = \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', null, true);
        $status = new \Resque\Job\Status($token);
        $this->assertEquals(\Resque\Job\Status::STATUS_WAITING, $status->get());
    }

    public function testRunningJobReturnsRunningStatus()
    {
        $token = \Resque\Resque::enqueue('jobs', '\Resque\Test\FailingJob', null, true);
        $job = $this->worker->reserve();
        $this->worker->workingOn($job);
        $status = new \Resque\Job\Status($token);
        $this->assertEquals(\Resque\Job\Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $token = \Resque\Resque::enqueue('jobs', '\Resque\Test\FailingJob', null, true);
        $this->worker->work(0);
        $status = new \Resque\Job\Status($token);
        $this->assertEquals(\Resque\Job\Status::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $token = \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', null, true);
        $this->worker->work(0);
        $status = new \Resque\Job\Status($token);
        $this->assertEquals(\Resque\Job\Status::STATUS_COMPLETE, $status->get());
    }

    public function testStatusIsNotTrackedWhenToldNotTo()
    {
        $token = \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', null, false);
        $status = new \Resque\Job\Status($token);
        $this->assertFalse($status->isTracking());
    }

    public function testStatusTrackingCanBeStopped()
    {
        \Resque\Job\Status::create('test');
        $status = new \Resque\Job\Status('test');
        $this->assertEquals(\Resque\Job\Status::STATUS_WAITING, $status->get());
        $status->stop();
        $this->assertFalse($status->get());
    }

    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $originalToken = \Resque\Resque::enqueue('jobs', '\Resque\Test\TestJob', null, true);
        $job = $this->worker->reserve();

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $this->worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        $newJob = \Resque\Job\Job::reserve('jobs');
        $this->assertEquals(\Resque\Job\Status::STATUS_WAITING, $newJob->getStatus());
    }
}