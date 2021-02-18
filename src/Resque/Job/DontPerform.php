<?php

namespace Resque\Job;

/**
 * Exception to be thrown if a job should not be performed/run.
 *
 * @package        Resque/Job
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class DontPerform extends \Exception
{

}
