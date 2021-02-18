<?php

namespace Resque\Test;

/**
 * \Resque\Log tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class LogTest extends TestCase
{
    public function testLogInterpolate()
    {
        $logger = new \Resque\Log();
        $actual = $logger->interpolate('string {replace}', ['replace' => 'value']);
        $expected = 'string value';

        $this->assertEquals($expected, $actual);
    }

    public function testLogInterpolateMutiple()
    {
        $logger = new \Resque\Log();
        $actual = $logger->interpolate(
            'string {replace1} {replace2}',
            ['replace1' => 'value1', 'replace2' => 'value2']
        );
        $expected = 'string value1 value2';

        $this->assertEquals($expected, $actual);
    }
}
