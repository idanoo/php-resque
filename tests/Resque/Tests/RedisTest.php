<?php

declare(strict_types=1);

namespace Resque\Test;

/**
 * \Resque\Event tests.
 *
 * @package        Resque/Tests
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class RedisTest extends TestCase
{
    public function testRedisGetSet()
    {
        $this->redis->set('testKey', 24, ['ex' => \Resque\Redis::DEFAULT_REDIS_TTL]);

        $val = $this->redis->get('testKey');
        static::assertEquals(24, $val);
    }

    public function testDefaultPrefixIsResque()
    {
        \Resque\Redis::prefix('resque');
        static::assertEquals('resque:', \Resque\Redis::getPrefix());
    }

    public function testPrefixAppendsTrailingColon()
    {
        \Resque\Redis::prefix('myapp');
        static::assertEquals('myapp:', \Resque\Redis::getPrefix());

        // A prefix that already ends in a colon is left untouched.
        \Resque\Redis::prefix('myapp:');
        static::assertEquals('myapp:', \Resque\Redis::getPrefix());

        // Restore the default so later tests are unaffected.
        \Resque\Redis::prefix('resque');
    }

    public function testKeyCommandsArePrefixedWithNamespace()
    {
        \Resque\Redis::prefix('resque');

        // \Resque\Redis prefixes keys transparently; the raw Credis client does not.
        \Resque\Resque::redis()->set('prefixed', 'value');
        static::assertEquals('value', $this->redis->get('resque:prefixed'));
    }

    /**
     * These DNS strings are considered valid.
     *
     * @return array
     */
    public static function validDsnStringProvider()
    {
        return [
            // Input , Expected output
            [
                '',
                [
                    'localhost',
                    \Resque\Redis::DEFAULT_PORT,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'localhost',
                [
                    'localhost',
                    \Resque\Redis::DEFAULT_PORT,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'localhost:1234',
                [
                    'localhost',
                    1234,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'localhost:1234/2',
                [
                    'localhost',
                    1234,
                    2,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'redis://foobar',
                [
                    'foobar',
                    \Resque\Redis::DEFAULT_PORT,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'redis://foobar/',
                [
                    'foobar',
                    \Resque\Redis::DEFAULT_PORT,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'redis://foobar:1234',
                [
                    'foobar',
                    1234,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'redis://foobar:1234/15',
                [
                    'foobar',
                    1234,
                    15,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'redis://foobar:1234/0',
                [
                    'foobar',
                    1234,
                    0,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'redis://user@foobar:1234',
                [
                    'foobar',
                    1234,
                    false,
                    'user',
                    false,
                    [],
                ],
            ],
            [
                'redis://user@foobar:1234/15',
                [
                    'foobar',
                    1234,
                    15,
                    'user',
                    false,
                    [],
                ],
            ],
            [
                'redis://user:pass@foobar:1234',
                [
                    'foobar',
                    1234,
                    false,
                    'user',
                    'pass',
                    [],
                ],
            ],
            [
                'redis://user:pass@foobar:1234?x=y&a=b',
                [
                    'foobar',
                    1234,
                    false,
                    'user',
                    'pass',
                    ['x' => 'y', 'a' => 'b'],
                ],
            ],
            [
                'redis://:pass@foobar:1234?x=y&a=b',
                [
                    'foobar',
                    1234,
                    false,
                    false,
                    'pass',
                    ['x' => 'y', 'a' => 'b'],
                ],
            ],
            [
                'redis://user@foobar:1234?x=y&a=b',
                [
                    'foobar',
                    1234,
                    false,
                    'user',
                    false,
                    ['x' => 'y', 'a' => 'b'],
                ],
            ],
            [
                'redis://foobar:1234?x=y&a=b',
                [
                    'foobar',
                    1234,
                    false,
                    false,
                    false,
                    ['x' => 'y', 'a' => 'b'],
                ],
            ],
            [
                'redis://user@foobar:1234/12?x=y&a=b',
                [
                    'foobar',
                    1234,
                    12,
                    'user',
                    false,
                    ['x' => 'y', 'a' => 'b'],
                ],
            ],
            [
                'tcp://user@foobar:1234/12?x=y&a=b',
                [
                    'foobar',
                    1234,
                    12,
                    'user',
                    false,
                    ['x' => 'y', 'a' => 'b'],
                ],
            ],
            [
                'unix:///tmp/redis.sock',
                [
                    'unix:///tmp/redis.sock',
                    null,
                    false,
                    null,
                    null,
                    null,
                ],
            ],
            // TLS schemes keep their transport prefix on the host for the driver
            [
                'rediss://foobar',
                [
                    'tls://foobar',
                    \Resque\Redis::DEFAULT_PORT,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            [
                'rediss://user:pass@foobar:1234/2?x=y',
                [
                    'tls://foobar',
                    1234,
                    2,
                    'user',
                    'pass',
                    ['x' => 'y'],
                ],
            ],
            [
                'tls://user:pass@foobar:1234',
                [
                    'tls://foobar',
                    1234,
                    false,
                    'user',
                    'pass',
                    [],
                ],
            ],
            [
                'ssl://foobar:1234',
                [
                    'ssl://foobar',
                    1234,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            // Schemes are matched case-insensitively
            [
                'REDISS://foobar:1234',
                [
                    'tls://foobar',
                    1234,
                    false,
                    false,
                    false,
                    [],
                ],
            ],
            // Credentials are percent-decoded
            [
                'redis://us%40er:p%40ss%3Aword@foobar:1234',
                [
                    'foobar',
                    1234,
                    false,
                    'us@er',
                    'p@ss:word',
                    [],
                ],
            ],
            // TLS options are left in the options array for parseTlsOptions() to pick up
            [
                'rediss://foobar:1234?tls_cafile=/etc/ssl/ca.pem&tls_verify_peer=0',
                [
                    'tls://foobar',
                    1234,
                    false,
                    false,
                    false,
                    ['tls_cafile' => '/etc/ssl/ca.pem', 'tls_verify_peer' => '0'],
                ],
            ],
        ];
    }

    /**
     * These DSN values should throw exceptions
     * @return array
     */
    public static function bogusDsnStringProvider()
    {
        return [
            ['http://foo.bar/'],
            ['user:@foobar:1234?x=y&a=b'],
            ['foobar:1234?x=y&a=b'],
            ['redis+tls://foobar:1234'],
            ['https://foobar:1234'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validDsnStringProvider')]
    public function testParsingValidDsnString($dsn, $expected)
    {
        $result = \Resque\Redis::parseDsn($dsn);
        static::assertEquals($expected, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bogusDsnStringProvider')]
    public function testParsingBogusDsnStringThrowsException($dsn)
    {
        $this->expectException(\InvalidArgumentException::class);
        \Resque\Redis::parseDsn($dsn);
    }

    public function testParseTlsOptionsIgnoresNonTlsOptions()
    {
        static::assertEquals(
            [],
            \Resque\Redis::parseTlsOptions(['timeout' => '5', 'persistent' => 'x', 'max_connect_retries' => '3'])
        );
    }

    public function testParseTlsOptionsCastsBooleanAndStringOptions()
    {
        static::assertEquals(
            [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'cafile' => '/etc/ssl/redis-ca.pem',
                'peer_name' => 'redis.internal',
            ],
            \Resque\Redis::parseTlsOptions([
                'tls_verify_peer' => '0',
                'tls_verify_peer_name' => 'false',
                'tls_allow_self_signed' => '1',
                'tls_cafile' => '/etc/ssl/redis-ca.pem',
                'tls_peer_name' => 'redis.internal',
            ])
        );
    }

    /**
     * @return array
     */
    public static function falseyTlsOptionValueProvider()
    {
        return [['0'], ['false'], ['FALSE'], ['off'], ['no'], ['']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('falseyTlsOptionValueProvider')]
    public function testParseTlsOptionsTreatsFalseyValuesAsDisabled($value)
    {
        static::assertEquals(
            ['verify_peer' => false],
            \Resque\Redis::parseTlsOptions(['tls_verify_peer' => $value])
        );
    }

    public function testParseTlsOptionsRejectsUnknownTlsOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        \Resque\Redis::parseTlsOptions(['tls_verify_pear' => '0']);
    }

    public function testTlsDsnBuildsAnEncryptedDriver()
    {
        $driver = self::driverForDsn('rediss://redis.internal:6380');

        static::assertTrue($driver->isTls());
        static::assertEquals('redis.internal', $driver->getHost());
        static::assertEquals(6380, $driver->getPort());
    }

    public function testPlaintextDsnBuildsAnUnencryptedDriver()
    {
        $driver = self::driverForDsn('redis://redis.internal:6380');

        static::assertFalse($driver->isTls());
        static::assertEquals('redis.internal', $driver->getHost());
    }

    public function testTlsOptionsFromDsnArePassedToTheDriver()
    {
        $driver = self::driverForDsn(
            'rediss://redis.internal?tls_cafile=/etc/ssl/redis-ca.pem&tls_verify_peer=0'
        );

        static::assertEquals(
            ['cafile' => '/etc/ssl/redis-ca.pem', 'verify_peer' => false],
            self::driverProperty($driver, 'tlsOptions')
        );
    }

    public function testPasswordOnlyDsnAuthenticatesWithoutAUsername()
    {
        $driver = self::driverForDsn('redis://:my-secret@redis.internal');

        static::assertEquals('my-secret', self::driverProperty($driver, 'authPassword'));
        static::assertNull(self::driverProperty($driver, 'authUsername'));
    }

    public function testUsernameAndPasswordDsnAuthenticatesWithAclCredentials()
    {
        $driver = self::driverForDsn('redis://resque:my-secret@redis.internal');

        static::assertEquals('my-secret', self::driverProperty($driver, 'authPassword'));
        static::assertEquals('resque', self::driverProperty($driver, 'authUsername'));
    }

    public function testPercentEncodedCredentialsAreDecodedForAuth()
    {
        $driver = self::driverForDsn('redis://us%40er:p%40ss%3Aword@redis.internal');

        static::assertEquals('p@ss:word', self::driverProperty($driver, 'authPassword'));
        static::assertEquals('us@er', self::driverProperty($driver, 'authUsername'));
    }

    public function testUsernameWithoutAPasswordIsNotUsedForAuth()
    {
        $driver = self::driverForDsn('redis://resque@redis.internal');

        static::assertNull(self::driverProperty($driver, 'authPassword'));
        static::assertNull(self::driverProperty($driver, 'authUsername'));
    }

    public function testCloseForcesAPersistentConnectionShut()
    {
        $driver = new FlakyRedisDriver(0, new \CredisException('unused'));
        $redis = new \Resque\Redis('redis://redis.internal', null, $driver);

        // Credis skips an unforced close while `persistent` is set, which would leave
        // the socket open across a fork for parent and child to fight over.
        $redis->close();

        static::assertEquals([true], $driver->closes);
    }

    public function testDroppedConnectionIsRetriedOnceOnAFreshConnection()
    {
        $driver = new FlakyRedisDriver(1, new \CredisException(
            'read error on connection to tcp://redis.internal:6379'
        ));
        $redis = new \Resque\Redis('redis://redis.internal', null, $driver);

        static::assertEquals('ok', $redis->get('testKey'));
        static::assertEquals(1, $driver->reconnects);
        static::assertEquals(['get', 'get'], $driver->calls);
    }

    public function testDroppedConnectionIsOnlyRetriedOnce()
    {
        $driver = new FlakyRedisDriver(2, new \CredisException('read error on connection'));
        $redis = new \Resque\Redis('redis://redis.internal', null, $driver);

        $this->expectException(\Resque\RedisException::class);

        try {
            $redis->get('testKey');
        } finally {
            static::assertEquals(1, $driver->reconnects);
            static::assertEquals(['get', 'get'], $driver->calls);
        }
    }

    public function testCommandErrorsAreNotRetried()
    {
        $driver = new FlakyRedisDriver(1, new \CredisException('WRONGTYPE Operation against a key'));
        $redis = new \Resque\Redis('redis://redis.internal', null, $driver);

        $this->expectException(\Resque\RedisException::class);

        try {
            $redis->get('testKey');
        } finally {
            static::assertEquals(0, $driver->reconnects);
            static::assertEquals(['get'], $driver->calls);
        }
    }

    public function testQueuedTransactionCommandsAreNotReplayedAfterADisconnect()
    {
        $driver = new FlakyRedisDriver(0, new \CredisException('read error on connection'));
        $redis = new \Resque\Redis('redis://redis.internal', null, $driver);
        $redis->multi();

        // Inside a MULTI the queued commands die with the connection, so replaying a
        // single command on a new one would silently drop the rest of the transaction.
        $reflection = new \ReflectionProperty(\Resque\Redis::class, 'inTransaction');
        static::assertTrue($reflection->getValue($redis));

        $driver->calls = [];
        $failing = new FlakyRedisDriver(1, new \CredisException('read error on connection'));
        $driverProperty = new \ReflectionProperty(\Resque\Redis::class, 'driver');
        $driverProperty->setValue($redis, $failing);

        $this->expectException(\Resque\RedisException::class);

        try {
            $redis->rpush('queue:jobs', 'payload');
        } finally {
            static::assertEquals(0, $failing->reconnects);
            static::assertEquals(['rpush'], $failing->calls);
        }
    }

    /**
     * Build a \Resque\Redis from a DSN and return its underlying driver.
     *
     * No database is passed, so the driver is configured but never connected — these
     * assertions do not need (or reach) a real Redis server.
     *
     * @param string $dsn
     *
     * @return \Credis_Client
     */
    private static function driverForDsn($dsn)
    {
        $property = new \ReflectionProperty(\Resque\Redis::class, 'driver');

        return $property->getValue(new \Resque\Redis($dsn));
    }

    /**
     * Read a protected \Credis_Client property that has no public accessor.
     *
     * @param \Credis_Client $driver
     * @param string $name
     *
     * @return mixed
     */
    private static function driverProperty($driver, $name)
    {
        $property = new \ReflectionProperty(\Credis_Client::class, $name);

        return $property->getValue($driver);
    }
}
