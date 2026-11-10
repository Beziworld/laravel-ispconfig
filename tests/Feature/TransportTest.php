<?php

declare(strict_types=1);

use Beziworld\ISPConfig\Exceptions\ApiException;
use Beziworld\ISPConfig\Exceptions\RateLimitException;
use Beziworld\ISPConfig\Facades\ISPConfig;
use Beziworld\ISPConfig\ISPConfigManager;
use Beziworld\ISPConfig\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends the configured user agent and a json body to the endpoint', function (): void {
    TestCase::api(['server_get_all' => TestCase::ok([])]);

    expect(ISPConfig::server()->all())->toBe([]);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '?server_get_all'
        && $request->method() === 'POST'
        && $request->hasHeader('User-Agent', 'beziworld-laravel-ispconfig/1.0')
        && $request->hasHeader('Accept', 'application/json')
        && $request->body() === '{"session_id":"' . TestCase::SESSION . '"}');
});

it('rejects a login answer without a session id', function (): void {
    TestCase::api(['login' => TestCase::ok(false)]);

    expect(fn() => ISPConfig::clients()->get(1))->toThrow(ApiException::class, 'ISPConfig login returned an unexpected response (HTTP 200).');

    Http::assertNotSent(fn(Request $request): bool => TestCase::functionOf($request) === 'client_get');
});

it('gives up on read functions after the second connection error', function (): void {
    $attempts = 0;
    TestCase::api(['client_get' => function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('connection refused');
    }]);

    try {
        ISPConfig::clients()->get(1);
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->errorCode())->toBe('connection_error')
            ->and($exception->function())->toBe('client_get')
            ->and($exception->httpStatus())->toBeNull()
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }

    expect($attempts)->toBe(2);
});

it('reports a persistent server error of a read function', function (): void {
    TestCase::api(['client_get' => fn() => Http::response('Service unavailable', 503)]);

    try {
        ISPConfig::clients()->get(1);
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->errorCode())->toBe('unexpected_response')
            ->and($exception->httpStatus())->toBe(503);
    }

    expect(Http::recorded(fn(Request $request): bool => TestCase::functionOf($request) === 'client_get'))->toHaveCount(2);
});

it('reports rate limiting without a retry delay', function (): void {
    TestCase::api(['client_get' => fn() => Http::response('', 429)]);

    try {
        ISPConfig::clients()->get(1);
        test()->fail('Expected exception was not thrown.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter())->toBeNull()
            ->and($exception->getCode())->toBe(429)
            ->and($exception->getMessage())->toBe('ISPConfig client_get was rate limited (HTTP 429).');
    }
});

it('keeps error codes other than remote_fault as the fault code', function (): void {
    TestCase::api(['client_add' => TestCase::fault('', 'invalid_data')]);

    try {
        ISPConfig::clients()->create(['username' => 'shop']);
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->errorCode())->toBe('invalid_data')
            ->and($exception->faultCode())->toBe('invalid_data')
            ->and($exception->getMessage())->toBe('ISPConfig client_add failed: invalid_data');
    }
});

it('drops the session when the session is rejected for another address', function (): void {
    TestCase::api(['client_get' => TestCase::fault('Session IP mismatch.')]);

    $connection = ISPConfig::connection()->connection();

    expect(fn() => ISPConfig::clients()->get(1))->toThrow(Beziworld\ISPConfig\Exceptions\AuthenticationException::class, 'session_ip_mismatch')
        ->and(cache()->get($connection->config()->sessionCacheKey()))->toBeNull();
});

it('treats an expired session as already logged out', function (): void {
    TestCase::api(['client_get' => TestCase::ok([]), 'logout' => TestCase::fault('The Session is expired or does not exist.')]);

    ISPConfig::clients()->get(1);

    expect(ISPConfig::logout())->toBeFalse();
});

it('rethrows other logout failures', function (): void {
    TestCase::api(['client_get' => TestCase::ok([]), 'logout' => TestCase::fault('This ISPConfig installation is currently under maintenance. We should be back shortly. Thank you for your patience.')]);

    ISPConfig::clients()->get(1);

    expect(fn() => ISPConfig::logout())->toThrow(ApiException::class, 'maintenance_mode');
});

it('logs out a session that is only known from the cache', function (): void {
    TestCase::api(['client_get' => TestCase::ok([])]);
    ISPConfig::clients()->get(1);

    TestCase::refake([]);

    expect(ISPConfig::logout())->toBeTrue();

    Http::assertSent(fn(Request $request): bool => TestCase::functionOf($request) === 'logout'
        && $request->data() === ['session_id' => TestCase::SESSION]);
});

it('rebuilds purged connections and honours the default connection setting', function (): void {
    $manager = app(ISPConfigManager::class);
    $first = $manager->connection();

    expect($manager->connection('default'))->toBe($first);

    $manager->purge();

    expect($manager->connection())->not->toBe($first);

    config()->set('ispconfig.default', 'backup');

    expect($manager->defaultConnection())->toBe('backup')
        ->and($manager->connection()->connection()->config()->username)->toBe('remote2');

    config()->set('ispconfig.default', '');

    expect($manager->defaultConnection())->toBe('default');
});

it('rejects methods that the client does not have', function (): void {
    expect(fn() => app(ISPConfigManager::class)->doesNotExist())->toThrow(BadMethodCallException::class, 'doesNotExist');
});

it('reports a failed version check in the check command', function (): void {
    TestCase::api(['server_get_app_version' => TestCase::fault('You do not have the permissions to access this function.')]);

    $this->artisan('ispconfig:check')
        ->expectsOutputToContain('permission_denied')
        ->assertFailed();
});

it('warns when the check command can not list servers', function (): void {
    TestCase::api([
        'server_get_app_version' => TestCase::ok([]),
        'server_get_all' => TestCase::fault('You do not have the permissions to access this function.'),
    ]);

    $this->artisan('ispconfig:check', ['connection' => 'backup'])
        ->expectsOutputToContain('Logged in to http://10.0.0.5:8081/remote/json.php as remote2')
        ->expectsOutputToContain('Servers are not listed')
        ->assertSuccessful();
});
