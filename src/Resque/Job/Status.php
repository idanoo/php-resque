<?php

namespace Resque\Job;

/**
 * Status tracker/information for a job.
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Status
{
    public const STATUS_WAITING = 1;
    public const STATUS_RUNNING = 2;
    public const STATUS_FAILED = 3;
    public const STATUS_COMPLETE = 4;

    /**
     * @var string The ID of the job this status class refers back to.
     */
    private $id;

    /**
     * @var mixed Cache variable if the status of this job is being monitored or not.
     *    True/false when checked at least once or null if not checked yet.
     */
    private $isTracking = null;

    /**
     * @var array Array of statuses that are considered final/complete.
     */
    private static $completeStatuses = [
        self::STATUS_FAILED,
        self::STATUS_COMPLETE
    ];

    /**
     * Setup a new instance of the job monitor class for the supplied job ID.
     *
     * @param string $id The ID of the job to manage the status for.
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Create a new status monitor item for the supplied job ID. Will create
     * all necessary keys in Redis to monitor the status of a job.
     *
     * @param string $id The ID of the job to monitor the status of.
     */
    public static function create($id)
    {
        $statusPacket = [
            'status' => self::STATUS_WAITING,
            'updated' => time(),
            'started' => time(),
        ];
        \Resque\Resque::redis()->set(
            'job:' . $id . ':status',
            json_encode($statusPacket),
            ['ex' => \Resque\Redis::DEFAULT_REDIS_TTL],
        );
    }

    /**
     * Check if we're actually checking the status of the loaded job status
     * instance.
     *
     * @return boolean True if the status is being monitored, false if not.
     */
    public function isTracking(): bool
    {
        if ($this->isTracking === false) {
            return false;
        }

        if (!\Resque\Resque::redis()->exists((string)$this)) {
            $this->isTracking = false;
            return false;
        }

        $this->isTracking = true;
        return true;
    }

    /**
     * Update the status indicator for the current job with a new status.
     *
     * @param int The status of the job (see constants in \Resque\Job\Status)
     */
    public function update($status)
    {
        if (!$this->isTracking()) {
            return;
        }

        $statusPacket = [
            'status' => $status,
            'updated' => time(),
        ];

        \Resque\Resque::redis()->set(
            (string)$this,
            json_encode($statusPacket),
            ['ex' => \Resque\Redis::DEFAULT_REDIS_TTL],
        );
    }

    /**
     * Fetch the status for the job being monitored.
     *
     * @return mixed False if the status is not being monitored, otherwise the status as
     *    as an integer, based on the \Resque\Job\Status constants.
     */
    public function get()
    {
        if (!$this->isTracking()) {
            return false;
        }

        $statusPacket = json_decode(\Resque\Resque::redis()->get((string)$this), true);
        if (!$statusPacket) {
            return false;
        }

        return $statusPacket['status'];
    }

    /**
     * Stop tracking the status of a job.
     *
     * @return void
     */
    public function stop(): void
    {
        \Resque\Resque::redis()->del((string)$this);
    }

    /**
     * Generate a string representation of this object.
     *
     * @return string String representation of the current job status class.
     */
    public function __toString(): string
    {
        return 'job:' . $this->id . ':status';
    }
}
