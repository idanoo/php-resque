<?php

declare(strict_types=1);

namespace Resque;

/**
 * Resque event/plugin system class
 *
 * @package        Resque/Event
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Event
{
    /**
     * @var array Array containing all registered callbacks, indexked by event name.
     */
    private static $events = [];

    /**
     * Raise a given event with the supplied data.
     *
     * @param string $event Name of event to be raised.
     * @param mixed $data Optional, any data that should be passed to each callback.
     *
     * @return true
     */
    public static function trigger($event, $data = null)
    {
        if (!is_array($data)) {
            $data = [$data];
        }

        if ((self::$events[$event] ?? []) === []) {
            return true;
        }

        foreach (self::$events[$event] as $callback) {
            if (!is_callable($callback)) {
                continue;
            }

            call_user_func_array($callback, array_values($data));
        }

        return true;
    }

    /**
     * Listen in on a given event to have a specified callback fired.
     *
     * @param string $event Name of event to listen on.
     * @param mixed $callback Any callback callable by call_user_func_array
     *
     * @return true
     */
    public static function listen($event, $callback)
    {
        self::$events[$event][] = $callback;
        return true;
    }

    /**
     * Stop a given callback from listening on a specific event.
     *
     * @param string $event Name of event.
     * @param mixed $callback The callback as defined when listen() was called.
     *
     * @return true
     */
    public static function stopListening($event, $callback)
    {
        $listeners = self::$events[$event] ?? null;
        if ($listeners === null) {
            return true;
        }

        $key = array_search($callback, $listeners, strict: true);
        if ($key !== false) {
            unset(self::$events[$event][$key]);
        }

        return true;
    }

    /**
     * Call all registered listeners.
     *
     * @return void
     */
    public static function clearListeners(): void
    {
        self::$events = [];
    }
}
