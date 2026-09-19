<?php

declare(strict_types=1);

namespace Resque;

/**
 * Set up phpredis connection
 *
 * @package        Resque/Redis
 * @author         Daniel Mason <daniel@m2.nz>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */

class Redis
{
    /**
     * Redis Client
     *
     * @var \Credis_Client
     */
    private $driver;

    /**
     * Redis namespace
     *
     * @var string
     */
    private static $defaultNamespace = 'resque:';

    /**
     * @var bool Inside a MULTI/pipeline, where the queued commands are lost with the
     *           connection and a single-command retry would silently drop them.
     */
    private $inTransaction = false;

    /**
     * A default host to connect to
     */
    public const DEFAULT_HOST = 'localhost';

    /**
     * The default Redis port
     */
    public const DEFAULT_PORT = 6379;

    /**
     * The default Redis Database number
     */
    public const DEFAULT_DATABASE = 0;

    /**
     * Default Redis TTL (2 days)
     */
    public const DEFAULT_REDIS_TTL = 172800;

    /**
     * DSN schemes that connect in the clear
     */
    private const PLAINTEXT_SCHEMES = ['redis', 'tcp'];

    /**
     * DSN schemes that connect over TLS, mapped to the transport prefix Credis expects
     */
    private const TLS_SCHEMES = [
        'rediss' => 'tls://',
        'tls' => 'tls://',
        'ssl' => 'ssl://',
    ];

    /**
     * Boolean `tls_`-prefixed DSN options, passed through as PHP SSL context options
     *
     * @see https://www.php.net/manual/en/context.ssl.php
     */
    private const TLS_BOOL_OPTIONS = [
        'verify_peer',
        'verify_peer_name',
        'allow_self_signed',
        'disable_compression',
    ];

    /**
     * String `tls_`-prefixed DSN options, passed through as PHP SSL context options
     *
     * @see https://www.php.net/manual/en/context.ssl.php
     */
    private const TLS_STRING_OPTIONS = [
        'cafile',
        'capath',
        'local_cert',
        'local_pk',
        'passphrase',
        'peer_name',
        'ciphers',
    ];

    /**
     * Values treated as `false` for a boolean DSN option
     */
    private const FALSEY_OPTION_VALUES = ['0', 'false', 'off', 'no', ''];

    /**
     * Lower-cased error message fragments that mean the connection dropped mid-command
     */
    private const DISCONNECT_ERRORS = [
        'read error on connection',
        'connection lost',
        'connection closed',
        'went away',
        'broken pipe',
        'socket error on read socket',
        'connection not established',
    ];

    /**
     * Commands that must never be replayed on a new connection, keyed for O(1) lookups
     */
    private const NON_RETRYABLE_COMMANDS = [
        'exec' => true,
        'discard' => true,
        'watch' => true,
        'unwatch' => true,
        'subscribe' => true,
        'psubscribe' => true,
        'unsubscribe' => true,
        'punsubscribe' => true,
    ];

    /**
     * @var array<string, bool> Lookup map of all Redis commands that supply a
     *    key as their first argument, keyed by command name for O(1) lookups.
     *    Used to prefix keys with the Resque namespace.
     */
    private $keyCommands = [
        'exists' => true,
        'del' => true,
        'type' => true,
        'keys' => true,
        'expire' => true,
        'ttl' => true,
        'move' => true,
        'set' => true,
        'setex' => true,
        'get' => true,
        'getset' => true,
        'setnx' => true,
        'incr' => true,
        'incrby' => true,
        'decr' => true,
        'decrby' => true,
        'rpush' => true,
        'lpush' => true,
        'llen' => true,
        'lrange' => true,
        'ltrim' => true,
        'lindex' => true,
        'lset' => true,
        'lrem' => true,
        'lpop' => true,
        'blpop' => true,
        'rpop' => true,
        'sadd' => true,
        'srem' => true,
        'spop' => true,
        'scard' => true,
        'sismember' => true,
        'smembers' => true,
        'srandmember' => true,
        'zadd' => true,
        'zrem' => true,
        'zrange' => true,
        'zrevrange' => true,
        'zrangebyscore' => true,
        'zcard' => true,
        'zscore' => true,
        'zremrangebyscore' => true,
        'sort' => true,
        'rename' => true,
        'rpoplpush' => true,
    ];

    /**
     * Set Redis namespace (prefix) default: resque
     *
     * @param string $namespace
     *
     * @return void
     */
    public static function prefix(string $namespace): void
    {
        if (substr($namespace, -1) !== ':' && $namespace != '') {
            $namespace .= ':';
        }

        self::$defaultNamespace = $namespace;
    }

    /**
     * @param string|array $server A DSN or array
     * @param int $database A database number to select. However, if we find a valid database number in the DSN the
     *                      DSN-supplied value will be used instead and this parameter is ignored.
     * @param object $client Optional \Credis_Client instance instantiated by you
     *
     * @throws \Resque\RedisException
     */
    public function __construct($server, $database = null, $client = null)
    {
        try {
            if (is_object($client)) {
                $this->driver = $client;
            } else {
                list($host, $port, $dsnDatabase, $user, $password, $options) = self::parseDsn($server);
                $options = is_array($options) ? $options : [];
                $timeout = isset($options['timeout']) ? intval($options['timeout']) : null;
                $persistent = isset($options['persistent']) ? $options['persistent'] : '';
                $maxRetries = isset($options['max_connect_retries']) ? $options['max_connect_retries'] : 0;
                $tlsOptions = self::parseTlsOptions($options);
                // Credentials are handed to the driver rather than AUTH'd here so that they
                // are replayed if the connection drops and Credis reconnects. A username is
                // only meaningful alongside a password (Redis 6+ ACL `AUTH user pass`).
                $password = ($password === false || $password === null || $password === '') ? null : $password;
                $user = ($password === null || $user === false || $user === null || $user === '') ? null : $user;
                $this->driver = new \Credis_Client(
                    $host,
                    $port,
                    $timeout,
                    $persistent,
                    0,
                    $password,
                    $user,
                    $tlsOptions === [] ? null : $tlsOptions
                );
                $this->driver->setMaxConnectRetries($maxRetries);
                // If we have found a database in our DSN, use it instead of the `$database`
                // value passed into the constructor.
                if ($dsnDatabase !== false) {
                    $database = $dsnDatabase;
                }
            }
            if ($database !== null) {
                $this->driver->select($database);
            }
        } catch (\Exception $e) {
            throw new RedisException('Error communicating with Redis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse a DSN string, which can have one of the following formats:
     *
     * - host:port
     * - redis://user:pass@host:port/db?option1=val1&option2=val2
     * - tcp://user:pass@host:port/db?option1=val1&option2=val2
     * - rediss://user:pass@host:port/db?option1=val1&option2=val2 (TLS)
     * - tls://user:pass@host:port/db (TLS, as does ssl://)
     * - unix:///path/to/redis.sock
     *
     * The 'user' part is only used when a password is also supplied, in which case it is
     * sent as a Redis 6+ ACL `AUTH user pass`. Both are percent-decoded, so credentials
     * containing reserved characters such as `@`, `:` or `/` must be percent-encoded.
     *
     * For a TLS scheme the returned host keeps its transport prefix (e.g. `tls://redis.internal`)
     * as that is how the underlying Credis driver is told to negotiate an encrypted connection.
     *
     * @param string $dsn A DSN string
     *
     * @return array An array of DSN compotnents, with 'false' values for any unknown components. e.g.
     *               [host, port, db, user, pass, options]
     */
    public static function parseDsn($dsn): array
    {
        if ($dsn == '') {
            // Use a sensible default for an empty DNS string
            $dsn = 'redis://' . self::DEFAULT_HOST;
        }
        if (substr($dsn, 0, 7) === 'unix://') {
            return [
                $dsn,
                null,
                false,
                null,
                null,
                null,
            ];
        }
        $parts = parse_url($dsn);

        // Check the URI scheme, and work out whether the connection should be encrypted
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'redis';
        $hostPrefix = '';
        if (!in_array($scheme, self::PLAINTEXT_SCHEMES, strict: true)) {
            if (!isset(self::TLS_SCHEMES[$scheme])) {
                $validSchemes = array_merge(
                    self::PLAINTEXT_SCHEMES,
                    array_keys(self::TLS_SCHEMES),
                    ['unix']
                );
                throw new \InvalidArgumentException(
                    "Invalid DSN. Supported schemes are " . implode(', ', $validSchemes)
                );
            }

            $hostPrefix = self::TLS_SCHEMES[$scheme];
        }

        // Allow simple 'hostname' format, which `parse_url` treats as a path, not host.
        if (!isset($parts['host']) && isset($parts['path'])) {
            $parts['host'] = $parts['path'];
            unset($parts['path']);
        }

        // Extract the port number as an integer
        $port = isset($parts['port']) ? intval($parts['port']) : self::DEFAULT_PORT;

        // Get the database from the 'path' part of the URI
        $database = false;
        if (isset($parts['path'])) {
            // Strip non-digit chars from path
            $database = intval(preg_replace('/[^0-9]/', '', $parts['path']));
        }

        // Extract any 'user' and 'pass' values, undoing any percent-encoding needed to
        // carry reserved characters through the URI
        $user = isset($parts['user']) ? rawurldecode($parts['user']) : false;
        $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : false;

        // Convert the query string into an associative array
        $options = [];
        if (isset($parts['query'])) {
            // Parse the query string into an array
            parse_str($parts['query'], $options);
        }

        return [
            $hostPrefix . $parts['host'],
            $port,
            $database,
            $user,
            $pass,
            $options,
        ];
    }

    /**
     * Pull the TLS context options out of the parsed DSN query options.
     *
     * Any `tls_`-prefixed option is treated as a PHP SSL context option of the same name
     * with the prefix removed, e.g. `?tls_cafile=/etc/ssl/redis-ca.pem&tls_verify_peer=0`.
     * Unrecognised `tls_` options are rejected rather than silently ignored, so that a
     * typo cannot quietly leave verification in a state you did not ask for.
     *
     * @param array $options The options array returned by {@see self::parseDsn()}
     *
     * @return array PHP SSL context options, empty when the DSN sets none
     *
     * @see https://www.php.net/manual/en/context.ssl.php
     */
    public static function parseTlsOptions(array $options): array
    {
        $tlsOptions = [];
        foreach ($options as $key => $value) {
            $key = (string)$key;
            if (!str_starts_with($key, 'tls_')) {
                continue;
            }

            $name = substr($key, 4);
            if (in_array($name, self::TLS_BOOL_OPTIONS, strict: true)) {
                $tlsOptions[$name] = !in_array(
                    strtolower((string)$value),
                    self::FALSEY_OPTION_VALUES,
                    strict: true
                );
                continue;
            }

            if (!in_array($name, self::TLS_STRING_OPTIONS, strict: true)) {
                throw new \InvalidArgumentException("Invalid DSN. Unknown TLS option '" . $key . "'");
            }

            $tlsOptions[$name] = (string)$value;
        }

        return $tlsOptions;
    }

    /**
     * Magic method to handle all function requests and prefix key based
     * operations with the {self::$defaultNamespace} key prefix.
     *
     * A command that fails because the connection dropped (a managed-Redis failover,
     * an idle-timeout reap, a node restart) is retried once on a fresh connection.
     * Retrying means a write whose reply was lost may be applied twice, which matches
     * the at-least-once delivery Resque already has; commands where a replay would be
     * wrong (transactions, WATCH, subscriptions) are never retried.
     *
     * @param string $name The name of the method called.
     * @param array $args Array of supplied arguments to the method.
     *
     * @return mixed Return value from Resident::call() based on the command.
     *
     * @throws \Resque\RedisException
     */
    public function __call($name, $args)
    {
        if (isset($this->keyCommands[$name])) {
            if (is_array($args[0])) {
                foreach ($args[0] as $i => $v) {
                    $args[0][$i] = self::$defaultNamespace . $v;
                }
            } else {
                $args[0] = self::$defaultNamespace . $args[0];
            }
        }

        $command = strtolower($name);
        $retryable = !$this->inTransaction && !isset(self::NON_RETRYABLE_COMMANDS[$command]);
        if ($command === 'multi' || $command === 'pipeline') {
            $this->inTransaction = true;
        } elseif ($command === 'exec' || $command === 'discard') {
            $this->inTransaction = false;
        }

        try {
            return $this->driver->__call($name, $args);
        } catch (\Exception $e) {
            if (!$retryable || !$this->isDisconnect($e)) {
                throw new RedisException('Error communicating with Redis: ' . $e->getMessage(), 0, $e);
            }

            try {
                $this->reconnect();

                return $this->driver->__call($name, $args);
            } catch (\Exception $retryError) {
                $this->inTransaction = false;
                throw new RedisException(
                    'Error communicating with Redis: ' . $retryError->getMessage(),
                    0,
                    $retryError
                );
            }
        }
    }

    /**
     * Drop the connection, forcing a persistent one closed too.
     *
     * Credis' own close() is a no-op while the `persistent` DSN option is set, so
     * without the force a socket survives \Resque\Resque::fork() and is then shared
     * by parent and child — interleaved commands on one socket desynchronise the
     * protocol and surface as a read error on whichever process reads next.
     */
    public function close(): void
    {
        $this->inTransaction = false;
        if (method_exists($this->driver, 'close')) {
            $this->driver->close(true);
        }
    }

    /**
     * Whether the exception means the connection died rather than the command failing.
     *
     * Credis flags a dropped phpredis connection with CODE_DISCONNECTED, but a
     * standalone-mode or mid-write failure only shows up in the message.
     */
    private function isDisconnect(\Exception $e): bool
    {
        if ($e instanceof \CredisException && $e->getCode() === \CredisException::CODE_DISCONNECTED) {
            return true;
        }

        $message = strtolower($e->getMessage());
        foreach (self::DISCONNECT_ERRORS as $fragment) {
            if (str_contains($message, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tear down the dead socket and dial again, replaying AUTH and SELECT.
     *
     * @throws \Exception if the driver cannot reconnect, or was supplied by the caller
     *                   and has no connect()
     */
    private function reconnect(): void
    {
        if (!method_exists($this->driver, 'close') || !method_exists($this->driver, 'connect')) {
            throw new \RuntimeException('Redis driver does not support reconnecting');
        }

        $this->driver->close(true);
        $this->driver->connect();
    }

    /**
     * Returns redis prefix
     *
     * @return string
     */
    public static function getPrefix(): string
    {
        return self::$defaultNamespace;
    }
}
