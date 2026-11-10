<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig;

use Beziworld\ISPConfig\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable settings of a single ISPConfig connection.
 */
final readonly class ConnectionConfig
{
    /**
     * Longest session lifetime accepted by ISPConfig (remote sessions end 1800 seconds after the login).
     */
    public const int MAX_SESSION_TTL = 1800;

    public function __construct(
        public string $name,
        public string $url,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
        public bool|string $verifySsl = true,
        public int $timeout = 60,
        public int $connectTimeout = 10,
        public int $sessionTtl = 1500,
        public ?string $cacheStore = null,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfiguration When the URL or the credentials are missing.
     */
    public static function fromArray(string $name, array $config): self
    {
        $url = self::string($config['url'] ?? null) ?? throw InvalidConfiguration::missing($name, 'url', 'ISPCONFIG_URL');

        return new self(
            name: $name,
            url: self::normalizeUrl($url),
            username: self::string($config['username'] ?? null) ?? throw InvalidConfiguration::missing($name, 'username', 'ISPCONFIG_USERNAME'),
            password: self::string($config['password'] ?? null) ?? throw InvalidConfiguration::missing($name, 'password', 'ISPCONFIG_PASSWORD'),
            verifySsl: self::verify($config['verify_ssl'] ?? true),
            timeout: self::positiveInt($config['timeout'] ?? null, 60),
            connectTimeout: self::positiveInt($config['connect_timeout'] ?? null, 10),
            sessionTtl: min(self::positiveInt($config['session_ttl'] ?? null, 1500), self::MAX_SESSION_TTL - 60),
            cacheStore: self::string($config['cache_store'] ?? null),
        );
    }

    /**
     * Completes a panel address to the URL of the JSON endpoint.
     *
     * "panel.example.com" becomes "https://panel.example.com:8080/remote/json.php".
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^https?://#i', $url) !== 1) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        $scheme = strtolower(is_array($parts) && isset($parts['scheme']) ? $parts['scheme'] : 'https');
        $host = is_array($parts) && isset($parts['host']) ? $parts['host'] : trim(substr($url, strlen($scheme) + 3), '/');
        $port = is_array($parts) && isset($parts['port']) ? $parts['port'] : 8080;
        $path = rtrim(is_array($parts) && isset($parts['path']) ? $parts['path'] : '', '/');

        if ($path === '') {
            $path = '/remote/json.php';
        } elseif (! str_ends_with($path, '.php')) {
            $path .= '/json.php';
        }

        return sprintf('%s://%s:%d%s', $scheme, $host, $port, $path);
    }

    /**
     * Cache key of the remote session of this connection.
     */
    public function sessionCacheKey(): string
    {
        return 'ispconfig:session:' . hash('sha256', $this->url . '|' . $this->username . '|' . $this->password);
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function verify(mixed $value): bool|string
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => trim($value),
            };
        }

        return true;
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
