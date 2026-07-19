<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Test fixture exception thrown by FailingJob.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class FailingJobException extends \Exception
{
}
