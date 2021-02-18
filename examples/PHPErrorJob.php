<?php

/** @noinspection PhpUndefinedFunctionInspection */

namespace Resque\Example;

class PHPErrorJob
{
    public function perform()
    {
        callToUndefinedFunction();
    }
}
