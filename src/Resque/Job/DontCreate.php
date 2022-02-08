<?php

namespace Resque\Job;

/**
 * Exception to be thrown if while enqueuing a job it should not be created.
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class DontCreate extends \Exception
{
}
