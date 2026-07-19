<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * Resque\Stat tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class StatTest extends TestCase
{
    public function testStatCanBeIncremented()
    {
        \Resque\Stat::incr('test_incr');
        \Resque\Stat::incr('test_incr');
        static::assertEquals(2, $this->redis->get('resque:stat:test_incr'));
    }

    public function testStatCanBeIncrementedByX()
    {
        \Resque\Stat::incr('test_incrX', 10);
        \Resque\Stat::incr('test_incrX', 11);
        static::assertEquals(21, $this->redis->get('resque:stat:test_incrX'));
    }

    public function testStatCanBeDecremented()
    {
        \Resque\Stat::incr('test_decr', 22);
        \Resque\Stat::decr('test_decr');
        static::assertEquals(21, $this->redis->get('resque:stat:test_decr'));
    }

    public function testStatCanBeDecrementedByX()
    {
        \Resque\Stat::incr('test_decrX', 22);
        \Resque\Stat::decr('test_decrX', 11);
        static::assertEquals(11, $this->redis->get('resque:stat:test_decrX'));
    }

    public function testGetStatByName()
    {
        \Resque\Stat::incr('test_get', 100);
        static::assertEquals(100, \Resque\Stat::get('test_get'));
    }

    public function testGetUnknownStatReturns0()
    {
        static::assertEquals(0, \Resque\Stat::get('test_get_unknown'));
    }

    // Tests with DISABLE_STATS=true

    public function testStatIncrNoOpWhenDisabled()
    {
        \Resque\Stat::setDisableStats(true);
        static::assertTrue(\Resque\Stat::incr('test_incr_disabled'));
        static::assertTrue(\Resque\Stat::incr('test_incr_disabled'));
        static::assertEmpty($this->redis->get('resque:stat:test_incr_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testStatIncrByXNoOpWhenDisabled()
    {
        \Resque\Stat::setDisableStats(true);
        static::assertTrue(\Resque\Stat::incr('test_incrX_disabled', 10));
        static::assertTrue(\Resque\Stat::incr('test_incrX_disabled', 11));
        static::assertEmpty($this->redis->get('resque:stat:test_incrX_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testStatDecrNoOpWhenDisabled()
    {
        \Resque\Stat::incr('test_decr_disabled', 22);
        \Resque\Stat::setDisableStats(true);
        static::assertTrue(\Resque\Stat::decr('test_decr_disabled'));
        static::assertEquals(22, $this->redis->get('resque:stat:test_decr_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testStatDecrByXNoOpWhenDisabled()
    {
        \Resque\Stat::incr('test_decrX_disabled', 22);
        \Resque\Stat::setDisableStats(true);
        static::assertTrue(\Resque\Stat::decr('test_decrX_disabled', 11));
        static::assertEquals(22, $this->redis->get('resque:stat:test_decrX_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testGetStatReturns0WhenDisabled()
    {
        \Resque\Stat::incr('test_get_disabled', 100);
        \Resque\Stat::setDisableStats(true);
        static::assertEquals(0, \Resque\Stat::get('test_get_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testGetUnknownStatReturns0WhenDisabled()
    {
        \Resque\Stat::setDisableStats(true);
        static::assertEquals(0, \Resque\Stat::get('test_get_unknown_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testClearStatNoOpWhenDisabled()
    {
        \Resque\Stat::incr('test_clear_disabled', 50);
        \Resque\Stat::setDisableStats(true);
        static::assertTrue(\Resque\Stat::clear('test_clear_disabled'));
        \Resque\Stat::setDisableStats(false);
        static::assertEquals(50, $this->redis->get('resque:stat:test_clear_disabled'));
    }
}
