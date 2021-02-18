<?php

namespace Resque\Job;

/**
 * Runtime exception class for a job that does not exit cleanly.
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class DirtyExitException extends \RuntimeException
{

}
