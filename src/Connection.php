<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig;

use Beziworld\ISPConfig\Exceptions\ApiException;
use Beziworld\ISPConfig\Exceptions\AuthenticationException;
use Beziworld\ISPConfig\Exceptions\RateLimitException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;

/**
 * Transport for the JSON endpoint of the ISPConfig remote API (remote/json.php).
 *
 * Every call is a POST to "json.php?<function>" with the named parameters as a
 * JSON object. The remote session is opened lazily with login(), kept in the
 * Laravel cache for the configured time and reopened once when ISPConfig
 * reports an expired session; the expired call is rejected before it runs,
 * so repeating it is safe. Failed logins are never repeated, because more
 * than 10 failures within 5 minutes block the client address.
 */
class Connection
{
    private ?string $sessionId = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
        private readonly ConnectionConfig $config,
    ) {}

    public function config(): ConnectionConfig
    {
        return $this->config;
    }

    /**
     * Calls a remote function inside the session of the connection.
     *
     * @param array<string, mixed> $parameters Named parameters without session_id.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws RateLimitException
     */
    public function call(string $function, array $parameters = []): mixed
    {
        try {
            return $this->send($function, ['session_id' => $this->sessionId()] + $parameters);
        } catch (ApiException $exception) {
            if (! $exception->isSessionExpired()) {
                throw $exception;
            }

            $this->forgetSession();

            return $this->send($function, ['session_id' => $this->sessionId()] + $parameters);
        }
    }

    /**
     * Returns the current session ID, logging in when there is none.
     */
    public function sessionId(): string
    {
        if ($this->sessionId !== null) {
            return $this->sessionId;
        }

        $cached = $this->store()->get($this->config->sessionCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $this->sessionId = $cached;
        }

        return $this->login();
    }

    /**
     * Opens a new remote session and stores its ID.
     *
     * @throws AuthenticationException When the login is refused.
     */
    public function login(): string
    {
        $sessionId = $this->send('login', [
            'username' => $this->config->username,
            'password' => $this->config->password,
        ]);

        if (! is_string($sessionId) || $sessionId === '') {
            throw ApiException::unexpectedResponse('login', 200);
        }

        $this->store()->put($this->config->sessionCacheKey(), $sessionId, $this->config->sessionTtl);

        return $this->sessionId = $sessionId;
    }

    /**
     * Ends the current session, if any. Returns false when no session was open.
     */
    public function logout(): bool
    {
        $sessionId = $this->sessionId ?? $this->store()->get($this->config->sessionCacheKey());
        $this->forgetSession();

        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        try {
            return $this->send('logout', ['session_id' => $sessionId]) === true;
        } catch (ApiException $exception) {
            if ($exception->isSessionExpired()) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Drops the stored session ID without calling logout.
     */
    public function forgetSession(): void
    {
        $this->sessionId = null;
        $this->store()->forget($this->config->sessionCacheKey());
    }

    /**
     * Sends a single request with the given body and returns the "response" value.
     *
     * Read functions are sent a second time after a connection error or an HTTP 5xx answer;
     * functions that change data are sent exactly once.
     *
     * @param array<string, mixed> $body
     */
    public function send(string $function, array $body): mixed
    {
        $attempts = self::isReadOnly($function) ? 2 : 1;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->http->createPendingRequest()
                    ->acceptJson()
                    ->withUserAgent('beziworld-laravel-ispconfig/1.0')
                    ->withOptions(['verify' => $this->config->verifySsl])
                    ->timeout($this->config->timeout)
                    ->connectTimeout($this->config->connectTimeout)
                    ->withBody(json_encode((object) $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json')
                    ->post($this->config->url . '?' . $function);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    Sleep::for(250)->milliseconds();

                    continue;
                }

                throw ApiException::transport($function, $exception);
            }

            if ($response->serverError() && $attempt < $attempts) {
                Sleep::for(250)->milliseconds();

                continue;
            }

            return $this->decode($function, $response);
        }
    }

    /**
     * Whether a remote function only reads data and may be repeated safely.
     */
    public static function isReadOnly(string $function): bool
    {
        return $function === 'get_function_list'
            || preg_match('/(_get|_list|_count|_data)$/', $function) === 1
            || str_contains($function, '_get_');
    }

    private function decode(string $function, Response $response): mixed
    {
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');

            throw RateLimitException::make($function, is_numeric($retryAfter) ? (int) $retryAfter : null);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['code']) || ! is_string($payload['code'])) {
            throw ApiException::unexpectedResponse($function, $response->status());
        }

        if ($payload['code'] !== 'ok') {
            $message = is_string($payload['message'] ?? null) ? $payload['message'] : '';
            $fault = $payload['code'] === 'remote_fault' ? ApiException::faultFromMessage($message) : $payload['code'];

            if ($function === 'login' || in_array($fault, ApiException::LOGIN_FAULTS, true)) {
                if ($function !== 'login') {
                    $this->forgetSession();
                }

                throw AuthenticationException::fromResponse($function, $payload['code'], $message, $response->status(), $payload);
            }

            throw ApiException::fromResponse($function, $payload['code'], $message, $response->status(), $payload);
        }

        return $payload['response'] ?? null;
    }

    private function store(): Cache
    {
        return $this->cache->store($this->config->cacheStore);
    }
}
