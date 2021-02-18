<?php

namespace Resque\Example;

class LongPHPJob
{
    public function perform()
    {
        sleep(600);
    }
}
