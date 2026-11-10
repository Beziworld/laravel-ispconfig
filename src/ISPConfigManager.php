<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig;

use BadMethodCallException;
use Beziworld\ISPConfig\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Resolves named ISPConfig connections defined in config/ispconfig.php.
 *
 * Calls not defined on the manager are forwarded to the default connection,
 * so ISPConfig::sites() and ISPConfig::connection('panel2')->sites() are
 * both valid.
 *
 * @mixin ISPConfigClient
 */
class ISPConfigManager
{
    /** @var array<string, ISPConfigClient> */
    private array $clients = [];

    public function __construct(
        private readonly Config $config,
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
    ) {}

    /**
     * @throws InvalidConfiguration When the connection is not defined or incomplete.
     */
    public function connection(?string $name = null): ISPConfigClient
    {
        $name ??= $this->defaultConnection();

        return $this->clients[$name] ??= $this->resolve($name);
    }

    /**
     * Creates a client for credentials that are not stored in the configuration file,
     * e.g. panels kept in the database.
     *
     * @param array{url: string, username: string, password: string, verify_ssl?: bool|string, timeout?: int, connect_timeout?: int, session_ttl?: int, cache_store?: ?string} $config
     */
    public function using(array $config, string $name = 'runtime'): ISPConfigClient
    {
        return new ISPConfigClient(new Connection($this->http, $this->cache, ConnectionConfig::fromArray($name, $config)));
    }

    public function defaultConnection(): string
    {
        $default = $this->config->get('ispconfig.default', 'default');

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /**
     * Drops a resolved connection so that it is rebuilt from the configuration.
     */
    public function purge(?string $name = null): void
    {
        unset($this->clients[$name ?? $this->defaultConnection()]);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $callable = [$this->connection(), $method];

        if (! is_callable($callable)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', ISPConfigClient::class, $method));
        }

        return $callable(...$arguments);
    }

    private function resolve(string $name): ISPConfigClient
    {
        $config = $this->config->get('ispconfig.connections.' . $name);

        if (! is_array($config)) {
            throw InvalidConfiguration::unknownConnection($name);
        }

        /** @var array<string, mixed> $config */
        return new ISPConfigClient(new Connection($this->http, $this->cache, ConnectionConfig::fromArray($name, $config)));
    }
}
