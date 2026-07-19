<?php

declare(strict_types=1);

namespace Resque\Job;

interface JobInterface
{
    /**
     * @return bool
     */
    public function perform();

    /**
     * @return void
     */
    public function setUp(): void;

    /**
     * @return void
     */
    public function tearDown(): void;
}
