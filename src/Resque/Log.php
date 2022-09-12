<?php

namespace Resque;

/**
 * Resque default logger PSR-3 compliant
 *
 * @package        Resque
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Log extends \Psr\Log\AbstractLogger
{
    public $logLevel;

    public function __construct($logLevel = 'warning')
    {
        $this->logLevel = strtolower($logLevel);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level PSR-3 log level constant, or equivalent string
     * @param string $message Message to log, may contain a { placeholder }
     * @param array $context Variables to replace { placeholder }
     * @return null
     */
    public function log($level, $message, array $context = [])
    {
        $logLevels = [
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug',
        ];

        /**
         *  Only log things with a higher level than the current log level.
         *  e.g If set as 'alert' will only alert for 'emergency' and 'alert' logs.
         */
        if (array_search($level, $logLevels) <= array_search($this->logLevel, $logLevels)) {
            fwrite(
                STDOUT,
                '[' . $level . '][' . date('Y-m-d H:i:s') . '] ' .
                    $this->interpolate($message, $context) . PHP_EOL
            );
        }
        return;
    }

    /**
     * Fill placeholders with the provided context
     * @author Jordi Boggiano j.boggiano@seld.be
     *
     * @param  string $message Message to be logged
     * @param  array $context Array of variables to use in message
     * @return string
     */
    public function interpolate($message, array $context = [])
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
