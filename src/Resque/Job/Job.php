<?php

namespace Resque\Job;

/**
 * Resque job.
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Job
{
    /**
     * @var string The name of the queue that this job belongs to.
     */
    public $queue;

    /**
     * @var \Resque\Worker Instance of the Resque worker running this job.
     */
    public $worker;

    /**
     * @var array Array containing details of the job.
     */
    public $payload;

    /**
     * @var object|\Resque\Job\JobInterface Instance of the class performing work for this job.
     */
    private $instance;

    /**
     * @var \Resque\Job\FactoryInterface
     */
    private $jobFactory;

    /**
     * Instantiate a new instance of a job.
     *
     * @param string $queue The queue that the job belongs to.
     * @param array $payload array containing details of the job.
     */
    public function __construct($queue, $payload)
    {
        $this->queue = $queue;
        $this->payload = $payload;
    }

    /**
     * Create a new job and save it to the specified queue.
     *
     * @param string $queue The name of the queue to place the job in.
     * @param string $class The name of the class that contains the code to execute the job.
     * @param array $args Any optional arguments that should be passed when the job is executed.
     * @param boolean $monitor Set to true to be able to monitor the status of a job.
     * @param string $id Unique identifier for tracking the job. Generated if not supplied.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function create($queue, $class, $args = null, $monitor = false, $id = null): string
    {
        if (is_null($id)) {
            $id = \Resque\Resque::generateJobId();
        }

        if ($args !== null && !is_array($args)) {
            throw new \InvalidArgumentException(
                'Supplied $args must be an array.'
            );
        }
        \Resque\Resque::push($queue, [
            'class' => $class,
            'args' => [$args],
            'id' => $id,
            'queue_time' => microtime(true),
        ]);

        if ($monitor) {
            Status::create($id);
        }

        return $id;
    }

    /**
     * Find the next available job from the specified queue and return an
     * instance of \Resque\Job\Job for it.
     *
     * @param string $queue The name of the queue to check for a job in.
     *
     * @return Job|null Null when there aren't any waiting jobs, instance of \Resque\Job\Job when a job was found.
     */
    public static function reserve($queue): ?Job
    {
        $payload = \Resque\Resque::pop($queue);
        if (!is_array($payload)) {
            return null;
        }

        return new Job($queue, $payload);
    }

    /**
     * Find the next available job from the specified queues using blocking list pop
     * and return an instance of \Resque\Job\Job for it.
     *
     * @param array $queues
     * @param int $timeout
     *
     * @return Job|null Null when there aren't any waiting jobs, instance of \Resque\Job\Job when a job was found.
     */
    public static function reserveBlocking(array $queues, $timeout = null): ?Job
    {
        $item = \Resque\Resque::blpop($queues, $timeout);
        if (!is_array($item)) {
            return null;
        }

        return new Job($item['queue'], $item['payload']);
    }

    /**
     * Update the status of the current job.
     *
     * @param int $status Status constant from \Resque\Job\Status indicating the current status of a job.
     *
     * @return bool
     */
    public function updateStatus($status): bool
    {
        if (empty($this->payload['id'])) {
            return false;
        }

        $statusInstance = new Status($this->payload['id']);
        $statusInstance->update($status);
        return true;
    }

    /**
     * Return the status of the current job.
     *
     * @return int The status of the job as one of the \Resque\Job\Status constants.
     */
    public function getStatus()
    {
        $status = new Status($this->payload['id']);
        return $status->get();
    }

    /**
     * Get the arguments supplied to this job.
     *
     * @return array Array of arguments.
     */
    public function getArguments()
    {
        if (!isset($this->payload['args'])) {
            return [];
        }

        return $this->payload['args'][0];
    }

    /**
     * Get the instantiated object for this job that will be performing work.
     *
     * @return \Resque\Job\JobInterface Instance of the object that this job belongs to.
     */
    public function getInstance()
    {
        if (isset($this->instance) && !is_null($this->instance)) {
            return $this->instance;
        }

        $this->instance = $this->getJobFactory()->create($this->payload['class'], $this->getArguments(), $this->queue);
        $this->instance->job = $this;
        return $this->instance;
    }

    /**
     * Actually execute a job by calling the perform method on the class
     * associated with the job with the supplied arguments.
     *
     * @return bool
     *
     * @throws \Resque\Exception When the job's class could not be found or it does not contain a perform method.
     */
    public function perform()
    {
        try {
            \Resque\Event::trigger('beforePerform', $this);

            $instance = $this->getInstance();
            if (method_exists($instance, 'setUp')) {
                $instance->setUp();
            }

            $instance->perform();

            if (method_exists($instance, 'tearDown')) {
                $instance->tearDown();
            }

            \Resque\Event::trigger('afterPerform', $this);
        } catch (DontPerform $e) {
            /** @noinspection PhpRedundantCatchClauseInspection */
            return false;
        }

        return true;
    }

    /**
     * Mark the current job as having failed.
     *
     * @param $exception
     */
    public function fail($exception)
    {
        \Resque\Event::trigger('onFailure', [
            'exception' => $exception,
            'job' => $this,
        ]);

        $this->updateStatus(Status::STATUS_FAILED);
        \Resque\Failure\Failure::create(
            $this->payload,
            $exception,
            $this->worker,
            $this->queue
        );
        \Resque\Stat::incr('failed');
        \Resque\Stat::incr('failed:' . $this->worker);
    }

    /**
     * Re-queue the current job.
     *
     * @return string
     */
    public function recreate()
    {
        $status = new Status($this->payload['id']);
        $monitor = false;
        if ($status->isTracking()) {
            $monitor = true;
        }

        return self::create($this->queue, $this->payload['class'], $this->getArguments(), $monitor);
    }

    /**
     * Generate a string representation used to describe the current job.
     *
     * @return string The string representation of the job.
     */
    public function __toString()
    {
        $name = [
            'Job{' . $this->queue . '}'
        ];
        if (!empty($this->payload['id'])) {
            $name[] = 'ID: ' . $this->payload['id'];
        }
        $name[] = $this->payload['class'];
        if (!empty($this->payload['args'])) {
            $name[] = json_encode($this->payload['args']);
        }
        return '(' . implode(' | ', $name) . ')';
    }

    /**
     * @param FactoryInterface $jobFactory
     *
     * @return Job
     */
    public function setJobFactory(FactoryInterface $jobFactory)
    {
        $this->jobFactory = $jobFactory;

        return $this;
    }

    /**
     * @return FactoryInterface
     */
    public function getJobFactory()
    {
        if (is_null($this->jobFactory)) {
            $this->jobFactory = new Factory();
        }

        return $this->jobFactory;
    }
}
