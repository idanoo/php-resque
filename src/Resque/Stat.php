<?php

declare(strict_types=1);

namespace Resque;

/**
 * Resque statistic management (jobs processed, failed, etc)
 *
 * @package        Resque
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Stat
{
    /**
     * @var boolean Whether to disable stats collection.
     */
    protected static $disableStats = false;

    /**
     * Set whether to disable stats collection.
     *
     * @param bool $disableStats Whether to disable stats collection.
     *
     * @return void
     */
    public static function setDisableStats(bool $disableStats): void
    {
        self::$disableStats = $disableStats;
    }

    /**
     * Increment several stats and delete keys in one pipelined round trip.
     *
     * Same semantics as {@see self::incr()} per stat: SET NX seeds the counter with its
     * TTL, INCRBY counts.
     *
     * @param string[] $stats Stat names to increment by 1
     * @param string[] $deleteKeys Keys (un-prefixed) to delete in the same round trip
     */
    public static function incrPipelined(array $stats, array $deleteKeys = []): void
    {
        if (self::$disableStats) {
            $stats = [];
        }

        Resque::redis()->pipelined(static function (Redis $redis) use ($stats, $deleteKeys): void {
            foreach ($stats as $stat) {
                $redis->set('stat:' . $stat, 0, ['ex' => Redis::DEFAULT_REDIS_TTL, 'nx']);
                $redis->incrby('stat:' . $stat, 1);
            }
            foreach ($deleteKeys as $key) {
                $redis->del($key);
            }
        });
    }

    /**
     * Get the value of the supplied statistic counter for the specified statistic.
     *
     * @param string $stat The name of the statistic to get the stats for.
     *
     * @return int Value of the statistic.
     */
    public static function get(string $stat): int
    {
        if (self::$disableStats) {
            return 0;
        }

        return (int)Resque::redis()->get('stat:' . $stat);
    }

    /**
     * Increment the value of the specified statistic by a certain amount (default is 1)
     *
     * @param string $stat The name of the statistic to increment.
     * @param int $by The amount to increment the statistic by.
     *
     * @return bool True if successful, false if not.
     */
    public static function incr(string $stat, int $by = 1): bool
    {
        if (self::$disableStats) {
            return true;
        }

        // Make sure we set a TTL by default
        $set = Resque::redis()->set(
            'stat:' . $stat,
            $by,
            ['ex' => Redis::DEFAULT_REDIS_TTL,  'nx'],
        );

        // If it already exists, return the incrby value
        if (!$set) {
            return (bool)Resque::redis()->incrby('stat:' . $stat, $by);
        }

        return true;
    }

    /**
     * Decrement the value of the specified statistic by a certain amount (default is 1)
     *
     * @param string $stat The name of the statistic to decrement.
     * @param int $by The amount to decrement the statistic by.
     *
     * @return bool True if successful, false if not.
     */
    public static function decr(string $stat, int $by = 1): bool
    {
        if (self::$disableStats) {
            return true;
        }

        return (bool)Resque::redis()->decrby('stat:' . $stat, $by);
    }

    /**
     * Delete a statistic with the given name.
     *
     * @param string $stat The name of the statistic to delete.
     *
     * @return bool True if successful, false if not.
     */
    public static function clear(string $stat): bool
    {
        if (self::$disableStats) {
            return true;
        }

        return (bool)Resque::redis()->del('stat:' . $stat);
    }
}
