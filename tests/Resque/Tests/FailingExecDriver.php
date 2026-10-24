<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Stub Credis driver whose first $failures EXECs throw, as a pipeline flushed onto a
 * dropped connection would, so \Resque\Redis::pipelined() replay can be tested.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class FailingExecDriver extends FlakyRedisDriver
{
    /** @var int Remaining EXECs to fail */
    private $execFailures;

    /** @var \Exception Thrown by a failing EXEC */
    private $execError;

    public function __construct(int $execFailures, \Exception $execError)
    {
        parent::__construct(0, $execError);
        $this->execFailures = $execFailures;
        $this->execError = $execError;
    }

    public function __call($name, $args)
    {
        if ($name === 'exec' && $this->execFailures > 0) {
            $this->calls[] = $name;
            $this->execFailures--;
            throw $this->execError;
        }

        return parent::__call($name, $args);
    }
}
