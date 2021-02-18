<?php

namespace Resque\Example;

class BadPHPJob
{
    public function perform()
    {
        throw new \Exception('Unable to run this job!');
    }
}
