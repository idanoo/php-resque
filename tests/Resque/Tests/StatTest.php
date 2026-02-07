<?php

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
        $this->assertEquals(2, $this->redis->get('resque:stat:test_incr'));
    }

    public function testStatCanBeIncrementedByX()
    {
        \Resque\Stat::incr('test_incrX', 10);
        \Resque\Stat::incr('test_incrX', 11);
        $this->assertEquals(21, $this->redis->get('resque:stat:test_incrX'));
    }

    public function testStatCanBeDecremented()
    {
        \Resque\Stat::incr('test_decr', 22);
        \Resque\Stat::decr('test_decr');
        $this->assertEquals(21, $this->redis->get('resque:stat:test_decr'));
    }

    public function testStatCanBeDecrementedByX()
    {
        \Resque\Stat::incr('test_decrX', 22);
        \Resque\Stat::decr('test_decrX', 11);
        $this->assertEquals(11, $this->redis->get('resque:stat:test_decrX'));
    }

    public function testGetStatByName()
    {
        \Resque\Stat::incr('test_get', 100);
        $this->assertEquals(100, \Resque\Stat::get('test_get'));
    }

    public function testGetUnknownStatReturns0()
    {
        $this->assertEquals(0, \Resque\Stat::get('test_get_unknown'));
    }

    // Tests with DISABLE_STATS=true

    public function testStatIncrNoOpWhenDisabled()
    {
        \Resque\Stat::setDisableStats(true);
        $this->assertTrue(\Resque\Stat::incr('test_incr_disabled'));
        $this->assertTrue(\Resque\Stat::incr('test_incr_disabled'));
        $this->assertEmpty($this->redis->get('resque:stat:test_incr_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testStatIncrByXNoOpWhenDisabled()
    {
        \Resque\Stat::setDisableStats(true);
        $this->assertTrue(\Resque\Stat::incr('test_incrX_disabled', 10));
        $this->assertTrue(\Resque\Stat::incr('test_incrX_disabled', 11));
        $this->assertEmpty($this->redis->get('resque:stat:test_incrX_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testStatDecrNoOpWhenDisabled()
    {
        \Resque\Stat::incr('test_decr_disabled', 22);
        \Resque\Stat::setDisableStats(true);
        $this->assertTrue(\Resque\Stat::decr('test_decr_disabled'));
        $this->assertEquals(22, $this->redis->get('resque:stat:test_decr_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testStatDecrByXNoOpWhenDisabled()
    {
        \Resque\Stat::incr('test_decrX_disabled', 22);
        \Resque\Stat::setDisableStats(true);
        $this->assertTrue(\Resque\Stat::decr('test_decrX_disabled', 11));
        $this->assertEquals(22, $this->redis->get('resque:stat:test_decrX_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testGetStatReturns0WhenDisabled()
    {
        \Resque\Stat::incr('test_get_disabled', 100);
        \Resque\Stat::setDisableStats(true);
        $this->assertEquals(0, \Resque\Stat::get('test_get_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testGetUnknownStatReturns0WhenDisabled()
    {
        \Resque\Stat::setDisableStats(true);
        $this->assertEquals(0, \Resque\Stat::get('test_get_unknown_disabled'));
        \Resque\Stat::setDisableStats(false);
    }

    public function testClearStatNoOpWhenDisabled()
    {
        \Resque\Stat::incr('test_clear_disabled', 50);
        \Resque\Stat::setDisableStats(true);
        $this->assertTrue(\Resque\Stat::clear('test_clear_disabled'));
        \Resque\Stat::setDisableStats(false);
        $this->assertEquals(50, $this->redis->get('resque:stat:test_clear_disabled'));
    }
}