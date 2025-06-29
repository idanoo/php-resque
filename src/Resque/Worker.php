<?php

declare(ticks=1);

namespace Resque;

use Psr\Log\LoggerInterface;

/**
 * Resque worker that handles checking queues for jobs, fetching them
 * off the queues, running them and handling the result.
 *
 * @package        Resque
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Worker
{
    /**
     * @var LoggerInterface Logging object that impliments the PSR-3 LoggerInterface
     */
    public $logger;

    /**
     * @var array Array of all associated queues for this worker.
     */
    private $queues = [];

    /**
     * @var string The hostname of this worker.
     */
    private $hostname;

    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    private $shutdown = false;

    /**
     * @var boolean True if this worker is paused.
     */
    private $paused = false;

    /**
     * @var string String identifying this worker.
     */
    private $id;

    /**
     * @var \Resque\Job\Job Current job, if any, being processed by this worker.
     */
    private $currentJob = null;

    /**
     * @var int Process ID of child worker processes.
     */
    private $child = null;

    /**
     * Instantiate a new worker, given a list of queues that it should be working
     * on. The list of queues should be supplied in the priority that they should
     * be checked for jobs (first come, first served)
     *
     * Passing a single '*' allows the worker to work on all queues in alphabetical
     * order. You can easily add new queues dynamically and have them worked on using
     * this method.
     *
     * @param string|array $queues String with a single queue name, array with multiple.
     */
    public function __construct($queues)
    {
        $this->logger = new Log();

        if (!is_array($queues)) {
            $queues = [$queues];
        }

        $this->queues = $queues;
        $this->hostname = php_uname('n');

        $this->id = $this->hostname . ':' . getmypid() . ':' . implode(',', $this->queues);
    }

    /**
     * Return all workers known to Resque as instantiated instances.
     *
     * @return array
     */
    public static function all(): array
    {
        $workers = Resque::redis()->smembers('workers');
        if (!is_array($workers)) {
            $workers = [];
        }

        $instances = [];
        foreach ($workers as $workerId) {
            $instances[] = self::find($workerId);
        }

        return $instances;
    }

    /**
     * Given a worker ID, check if it is registered/valid.
     *
     * @param string $workerId ID of the worker
     *
     * @return boolean True if the worker exists, false if not
     *
     * @throws Resque_RedisException
     */
    public static function exists($workerId): bool
    {
        return (bool)Resque::redis()->sismember('workers', $workerId);
    }

    /**
     * Given a worker ID, find it and return an instantiated worker class for it.
     *
     * @param string $workerId The ID of the worker
     *
     * @return Resque_Worker|bool
     *
     * @throws Resque_RedisException
     */
    public static function find($workerId)
    {
        if (false === strpos($workerId, ":") || !self::exists($workerId)) {
            return false;
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        list($hostname, $pid, $queues) = explode(':', $workerId, 3);
        $queues = explode(',', $queues);
        $worker = new self($queues);
        $worker->setId($workerId);

        return $worker;
    }

    /**
     * Set the ID of this worker to a given ID string.
     *
     * @param string $workerId ID for the worker.
     *
     * @return void
     */
    public function setId($workerId): void
    {
        $this->id = $workerId;
    }

    /**
     * The primary loop for a worker which when called on an instance starts
     * the worker's life cycle.
     *
     * Queues are checked every $interval (seconds) for new jobs.
     *
     * @param int $interval How often to check for new jobs across the queues.
     * @param bool $blocking
     *
     * @return void
     *
     * @throws Resque_RedisException
     */
    public function work($interval = Resque::DEFAULT_INTERVAL, $blocking = false): void
    {
        $this->updateProcLine('Starting');
        $this->startup();

        while (true) {
            if ($this->shutdown) {
                break;
            }

            // Attempt to find and reserve a job
            $job = false;
            if (!$this->paused) {
                if ($blocking === true) {
                    $this->logger->log(
                        \Psr\Log\LogLevel::INFO,
                        'Starting blocking with timeout of {interval}',
                        ['interval' => $interval],
                    );
                    $this->updateProcLine(
                        'Waiting for ' . implode(',', $this->queues) . ' with blocking timeout ' . $interval
                    );
                } else {
                    $this->updateProcLine('Waiting for ' . implode(',', $this->queues) . ' with interval ' . $interval);
                }

                $job = $this->reserve($blocking, $interval);
            }

            if (!$job) {
                // For an interval of 0, break now - helps with unit testing etc
                if ($interval == 0) {
                    break;
                }

                if ($blocking === false) {
                    // If no job was found, we sleep for $interval before continuing and checking again
                    $this->logger->log(
                        \Psr\Log\LogLevel::INFO,
                        'Sleeping for {interval}',
                        ['interval' => $interval],
                    );

                    if ($this->paused) {
                        $this->updateProcLine('Paused');
                    } else {
                        $this->updateProcLine('Waiting for ' . implode(',', $this->queues));
                    }

                    usleep($interval * 1000000);
                }

                continue;
            }

            $this->logger->log(\Psr\Log\LogLevel::NOTICE, 'Starting work on {job}', ['job' => $job]);
            Event::trigger('beforeFork', $job);
            $this->workingOn($job);

            $this->child = Resque::fork();

            // Forked and we're the child. Run the job.
            if ($this->child === 0 || $this->child === false) {
                $status = 'Processing ' . $job->queue
                    . ' (' . ($job->payload['class'] ?? '') . ') since '
                    . date('Y-m-d H:i:s');
                $this->updateProcLine($status);
                $this->logger->log(\Psr\Log\LogLevel::INFO, $status);
                /** @noinspection PhpParamsInspection */
                $this->perform($job);
                if ($this->child === 0) {
                    exit(0);
                }
            }

            if ($this->child > 0) {
                // Parent process, sit and wait
                $status = 'Forked ' . $this->child . ' at ' . date('Y-m-d H:i:s');
                $this->updateProcLine($status);
                $this->logger->log(\Psr\Log\LogLevel::INFO, $status);

                // Wait until the child process finishes before continuing
                pcntl_wait($status);
                $exitStatus = pcntl_wexitstatus($status);
                if ($exitStatus !== 0) {
                    $job->fail(new \Resque\Job\DirtyExitException(
                        'Job exited with exit code ' . $exitStatus
                    ));
                }
            }

            $this->child = null;
            $this->doneWorking();
        }

        $this->unregisterWorker();
    }

    /**
     * Process a single job
     *
     * @param \Resque\Job\Job $job The job to be processed
     *
     * @return void
     */
    public function perform(\Resque\Job\Job $job): void
    {
        try {
            Event::trigger('afterFork', $job);
            $job->perform();
        } catch (\Exception $e) {
            $this->logger->log(\Psr\Log\LogLevel::CRITICAL, '{job} has failed {stack}', ['job' => $job, 'stack' => $e]);
            $job->fail($e);
            return;
        }

        $job->updateStatus(\Resque\Job\Status::STATUS_COMPLETE);
        $this->logger->log(\Psr\Log\LogLevel::NOTICE, '{job} has finished', ['job' => $job]);
    }

    /**
     * @param bool $blocking
     * @param int $timeout
     *
     * @return object|boolean - Instance of \Resque\Job\Job if a job is found, false if not
     */
    public function reserve($blocking = false, $timeout = null)
    {
        $queues = $this->queues();
        if (!is_array($queues)) {
            return false;
        }

        if ($blocking === true) {
            $job = \Resque\Job\Job::reserveBlocking($queues, $timeout);
            if (!is_null($job)) {
                $this->logger->log(\Psr\Log\LogLevel::INFO, 'Found job on {queue}', ['queue' => $job->queue]);
                return $job;
            }
        } else {
            foreach ($queues as $queue) {
                $this->logger->log(\Psr\Log\LogLevel::INFO, 'Checking {queue} for jobs', ['queue' => $queue]);
                $job = \Resque\Job\Job::reserve($queue);
                if (!is_null($job)) {
                    $this->logger->log(\Psr\Log\LogLevel::INFO, 'Found job on {queue}', ['queue' => $job->queue]);
                    return $job;
                }
            }
        }

        return false;
    }

    /**
     * Return an array containing all of the queues that this worker should use
     * when searching for jobs
     *
     * If * is found in the list of queues, every queue will be searched in
     * alphabetic order. (@param boolean $fetch If true, and the queue is set to *, will fetch
     * all queue names from redis
     *
     * @param boolean $fetch
     *
     * @return array Array of associated queues
     */
    public function queues(bool $fetch = true): array
    {
        if (!in_array('*', $this->queues) || $fetch == false) {
            return $this->queues;
        }

        $queues = Resque::queues();
        sort($queues);

        return $queues;
    }

    /**
     * Perform necessary actions to start a worker
     *
     * @return void
     */
    private function startup(): void
    {
        $this->registerSigHandlers();
        $this->pruneDeadWorkers();
        Event::trigger('beforeFirstFork', $this);
        $this->registerWorker();
    }

    /**
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     *
     * @param string $status The updated process title
     *
     * @return void
     */
    private function updateProcLine($status): void
    {
        $processTitle = 'resque-' . Resque::VERSION . ': ' . $status;
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        } elseif (function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     *
     * @return void
     */
    private function registerSigHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, [$this, 'shutDownNow']);
        pcntl_signal(SIGINT, [$this, 'shutDownNow']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        pcntl_signal(SIGUSR1, [$this, 'killChild']);
        pcntl_signal(SIGUSR2, [$this, 'pauseProcessing']);
        pcntl_signal(SIGCONT, [$this, 'unPauseProcessing']);
        $this->logger->log(\Psr\Log\LogLevel::DEBUG, 'Registered signals');
    }

    /**
     * Signal handler callback for USR2, pauses processing of new jobs
     *
     * @return void
     */
    public function pauseProcessing(): void
    {
        $this->logger->log(\Psr\Log\LogLevel::NOTICE, 'USR2 received; pausing job processing');
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     *
     * @return void
     */
    public function unPauseProcessing(): void
    {
        $this->logger->log(\Psr\Log\LogLevel::NOTICE, 'CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     *
     * @return void
     */
    public function shutdown(): void
    {
        $this->shutdown = true;
        $this->logger->log(\Psr\Log\LogLevel::NOTICE, 'Shutting down');
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     *
     * @return void
     */
    public function shutdownNow(): void
    {
        $this->shutdown();
        $this->killChild();
    }

    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     *
     * @return void
     */
    public function killChild(): void
    {
        if (!$this->child) {
            $this->logger->log(\Psr\Log\LogLevel::DEBUG, 'No child to kill.');
            return;
        }

        $this->logger->log(\Psr\Log\LogLevel::INFO, 'Killing child at {child}', ['child' => $this->child]);
        if (exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            $this->logger->log(\Psr\Log\LogLevel::DEBUG, 'Child {child} found, killing.', ['child' => $this->child]);
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        } else {
            $this->logger->log(
                \Psr\Log\LogLevel::INFO,
                'Child {child} not found, restarting.',
                ['child' => $this->child],
            );
            $this->shutdown();
        }
    }

    /**
     * Look for any workers which should be running on this server and if
     * they're not, remove them from Redis.
     *
     * This is a form of garbage collection to handle cases where the
     * server may have been killed and the Resque workers did not die gracefully
     * and therefore leave state information in Redis.
     *
     * @return void
     */
    public function pruneDeadWorkers(): void
    {
        $workerPids = $this->workerPids();
        $workers = self::all();
        foreach ($workers as $worker) {
            if (is_object($worker)) {
                list($host, $pid, $queues) = explode(':', (string)$worker, 3);
                if ($host != $this->hostname || in_array($pid, $workerPids) || $pid == getmypid()) {
                    continue;
                }
                $this->logger->log(
                    \Psr\Log\LogLevel::INFO,
                    'Pruning dead worker: {worker}',
                    ['worker' => (string)$worker],
                );
                $worker->unregisterWorker();
            }
        }
    }

    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * @return array Array of Resque worker process IDs.
     */
    public function workerPids(): array
    {
        $pids = [];
        exec('ps -A -o pid,command | grep [r]esque', $cmdOutput);
        foreach ($cmdOutput as $line) {
            list($pids[],) = explode(' ', trim($line), 2);
        }
        return $pids;
    }

    /**
     * Register this worker in Redis.
     * 48 hour TTL so we don't pollute the redis db on server termination.
     *
     * @return void
     */
    public function registerWorker(): void
    {
        Resque::redis()->sadd('workers', (string)$this);
        Resque::redis()->set(
            'worker:' . (string)$this . ':started',
            date('D M d H:i:s T Y'),
            ['ex' => Redis::DEFAULT_REDIS_TTL],
        );
    }

    /**
     * Unregister this worker in Redis. (shutdown etc)
     *
     * @return void
     */
    public function unregisterWorker(): void
    {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new \Resque\Job\DirtyExitException());
        }

        $id = (string)$this;
        Resque::redis()->srem('workers', $id);
        Resque::redis()->del('worker:' . $id);
        Resque::redis()->del('worker:' . $id . ':started');
        Stat::clear('processed:' . $id);
        Stat::clear('failed:' . $id);
    }

    /**
     * Tell Redis which job we're currently working on
     *
     * @param \Resque\Job\Job $job \Resque\Job\Job instance containing the job we're working on
     *
     * @return void
     *
     * @throws Resque_RedisException
     */
    public function workingOn(\Resque\Job\Job $job): void
    {
        $job->worker = $this;
        $this->currentJob = $job;
        $job->updateStatus(\Resque\Job\Status::STATUS_RUNNING);
        $data = json_encode([
            'queue' => $job->queue,
            'run_at' => date('D M d H:i:s T Y'),
            'payload' => $job->payload
        ]);

        Resque::redis()->set(
            'worker:' . $job->worker,
            $data,
            ['ex' => Redis::DEFAULT_REDIS_TTL],
        );
    }

    /**
     * Notify Redis that we've finished working on a job, clearing the working
     * state and incrementing the job stats
     *
     * @return void
     */
    public function doneWorking(): void
    {
        $this->currentJob = null;
        Stat::incr('processed');
        Stat::incr('processed:' . (string)$this);
        Resque::redis()->del('worker:' . (string)$this);
    }

    /**
     * Generate a string representation of this worker
     *
     * @return string String identifier for this worker instance
     */
    public function __toString(): string
    {
        return (string) $this->id;
    }

    /**
     * Return an object describing the job this worker is currently working on
     *
     * @return array Array with details of current job
     */
    public function job(): array
    {
        $job = Resque::redis()->get('worker:' . $this);

        return $job ? json_decode($job, true) : [];
    }

    /**
     * Get a statistic belonging to this worker
     *
     * @param string $stat Statistic to fetch.
     *
     * @return int Statistic value.
     */
    public function getStat(string $stat): int
    {
        return \Resque\Stat::get($stat . ':' . $this);
    }

    /**
     * Inject the logging object into the worker
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
