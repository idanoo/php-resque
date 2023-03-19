<?php

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
     * Get the value of the supplied statistic counter for the specified statistic.
     *
     * @param string $stat The name of the statistic to get the stats for.
     *
     * @return int Value of the statistic.
     */
    public static function get(string $stat): int
    {
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
        // Make sure we set a TTL by default
        $set = Resque::redis()->set(
            'stat:' . $stat,
            $by,
            ['ex' => time() + 86400, 'nx'],
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
        return (bool)Resque::redis()->del('stat:' . $stat);
    }
}
