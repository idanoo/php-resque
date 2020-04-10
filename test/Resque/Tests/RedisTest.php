<?php

/**
 * Resque_Event tests.
 *
 * @package        Resque/Tests
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Resque_Tests_RedisTest extends Resque_Tests_TestCase
{
    public function testRedisGetSet()
    {
        $this->redis->set("testKey", 24);
        $val = $this->redis->get("testKey");
        $this->assertEquals(24, $val);
    }

    /**
     * These DNS strings are considered valid.
     *
     * @return array
     */
    public function validDsnStringProvider()
    {
        return [
            // Input , Expected output
            ['', [
                'localhost',
                Resque_Redis::DEFAULT_PORT,
                false,
                false, false,
                [],
            ]],
            ['localhost', [
                'localhost',
                Resque_Redis::DEFAULT_PORT,
                false,
                false, false,
                [],
            ]],
            ['localhost:1234', [
                'localhost',
                1234,
                false,
                false, false,
                [],
            ]],
            ['localhost:1234/2', [
                'localhost',
                1234,
                2,
                false, false,
                [],
            ]],
            ['redis://foobar', [
                'foobar',
                Resque_Redis::DEFAULT_PORT,
                false,
                false, false,
                [],
            ]],
            ['redis://foobar/', [
                'foobar',
                Resque_Redis::DEFAULT_PORT,
                false,
                false, false,
                [],
            ]],
            ['redis://foobar:1234', [
                'foobar',
                1234,
                false,
                false, false,
                [],
            ]],
            ['redis://foobar:1234/15', [
                'foobar',
                1234,
                15,
                false, false,
                [],
            ]],
            ['redis://foobar:1234/0', [
                'foobar',
                1234,
                0,
                false, false,
                [],
            ]],
            ['redis://user@foobar:1234', [
                'foobar',
                1234,
                false,
                'user', false,
                [],
            ]],
            ['redis://user@foobar:1234/15', [
                'foobar',
                1234,
                15,
                'user', false,
                [],
            ]],
            ['redis://user:pass@foobar:1234', [
                'foobar',
                1234,
                false,
                'user', 'pass',
                [],
            ]],
            ['redis://user:pass@foobar:1234?x=y&a=b', [
                'foobar',
                1234,
                false,
                'user', 'pass',
                ['x' => 'y', 'a' => 'b'],
            ]],
            ['redis://:pass@foobar:1234?x=y&a=b', [
                'foobar',
                1234,
                false,
                false, 'pass',
                ['x' => 'y', 'a' => 'b'],
            ]],
            ['redis://user@foobar:1234?x=y&a=b', [
                'foobar',
                1234,
                false,
                'user', false,
                ['x' => 'y', 'a' => 'b'],
            ]],
            ['redis://foobar:1234?x=y&a=b', [
                'foobar',
                1234,
                false,
                false, false,
                ['x' => 'y', 'a' => 'b'],
            ]],
            ['redis://user@foobar:1234/12?x=y&a=b', [
                'foobar',
                1234,
                12,
                'user', false,
                ['x' => 'y', 'a' => 'b'],
            ]],
            ['tcp://user@foobar:1234/12?x=y&a=b', [
                'foobar',
                1234,
                12,
                'user', false,
                ['x' => 'y', 'a' => 'b'],
            ]],
        ];
    }

    /**
     * These DSN values should throw exceptions
     * @return array
     */
    public function bogusDsnStringProvider()
    {
        return [
            ['http://foo.bar/'],
            ['user:@foobar:1234?x=y&a=b'],
            ['foobar:1234?x=y&a=b'],
        ];
    }

    /**
     * @dataProvider validDsnStringProvider
     * @param $dsn
     * @param $expected
     */
    public function testParsingValidDsnString($dsn, $expected)
    {
        $result = Resque_Redis::parseDsn($dsn);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider bogusDsnStringProvider
     *
     * @expectedException InvalidArgumentException
     *
     * @param $dsn
     */
    public function testParsingBogusDsnStringThrowsException($dsn)
    {
        $this->expectException(InvalidArgumentException::class);
        Resque_Redis::parseDsn($dsn);
    }
}