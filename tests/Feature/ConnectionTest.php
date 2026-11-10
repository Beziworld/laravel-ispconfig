<?php

declare(strict_types=1);

use Beziworld\ISPConfig\Exceptions\ApiException;
use Beziworld\ISPConfig\Exceptions\AuthenticationException;
use Beziworld\ISPConfig\Exceptions\InvalidConfiguration;
use Beziworld\ISPConfig\Exceptions\RateLimitException;
use Beziworld\ISPConfig\Facades\ISPConfig;
use Beziworld\ISPConfig\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

$client = ['client_id' => '1', 'username' => 'shop', 'company_name' => 'Shop Ltd.', 'email' => 'office@shop.example', 'limit_client' => '0', 'locked' => 'n', 'canceled' => 'n', 'created_at' => '1762770000'];

it('logs in once and sends named json parameters', function () use ($client): void {
    TestCase::api(['client_get' => TestCase::ok($client)]);

    $first = ISPConfig::clients()->get(1);
    ISPConfig::clients()->get(1);

    expect($first?->username)->toBe('shop')
        ->and($first?->createdAt?->getTimestamp())->toBe(1762770000);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '?login'
        && $request->method() === 'POST'
        && $request->hasHeader('Content-Type', 'application/json')
        && $request->data() === ['username' => 'remote', 'password' => 'remote-secret']);
    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::URL . '?client_get'
        && $request->data() === ['session_id' => TestCase::SESSION, 'client_id' => 1]);
    Http::assertSentCount(3);
});

it('reuses the cached session in a new manager instance', function () use ($client): void {
    TestCase::api(['client_get' => TestCase::ok($client)]);
    ISPConfig::clients()->get(1);

    TestCase::refake(['client_get' => TestCase::ok($client)]);
    ISPConfig::clients()->get(1);

    Http::assertNotSent(fn(Request $request): bool => TestCase::functionOf($request) === 'login');
});

it('logs in again once when the session expired', function () use ($client): void {
    TestCase::api([
        'login' => [TestCase::ok('expired-session'), TestCase::ok(TestCase::SESSION)],
        'client_get' => [TestCase::fault('The Session is expired or does not exist.'), TestCase::ok($client)],
    ]);

    expect(ISPConfig::clients()->get(1)?->id)->toBe(1);

    $logins = Http::recorded(fn(Request $request): bool => TestCase::functionOf($request) === 'login');
    $calls = Http::recorded(fn(Request $request): bool => TestCase::functionOf($request) === 'client_get')->values();

    expect($logins)->toHaveCount(2)
        ->and($calls)->toHaveCount(2)
        ->and($calls[1][0]->data()['session_id'] ?? null)->toBe(TestCase::SESSION);
});

it('never repeats a refused login', function (): void {
    TestCase::api(['login' => TestCase::fault('The login failed. Username or password wrong.')]);

    try {
        ISPConfig::clients()->all();
        test()->fail('Expected exception was not thrown.');
    } catch (AuthenticationException $exception) {
        expect($exception->faultCode())->toBe('login_failed')
            ->and($exception->function())->toBe('login');
    }

    Http::assertSentCount(1);
});

it('reports the failure limit as an authentication error', function (): void {
    TestCase::api(['client_get' => TestCase::fault('The Session failure limit has been reached.')]);

    expect(fn() => ISPConfig::clients()->get(1))->toThrow(AuthenticationException::class, 'session_failure_limit');
});

it('maps permission and validation faults', function (): void {
    TestCase::api([
        'dns_zone_get' => TestCase::fault('You do not have the permissions to access this function.'),
        'sites_web_domain_add' => TestCase::fault("Invalid value for the field server_php_id.\r\n"),
    ]);

    try {
        ISPConfig::dns()->zones();
    } catch (ApiException $exception) {
        expect($exception->isPermissionDenied())->toBeTrue()
            ->and($exception->errorCode())->toBe('remote_fault')
            ->and($exception->getMessage())->toBe('ISPConfig dns_zone_get failed: You do not have the permissions to access this function. [permission_denied]');
    }

    try {
        ISPConfig::sites()->create(1, ['domain' => 'example.com']);
    } catch (ApiException $exception) {
        expect($exception->isValidationError())->toBeTrue()
            ->and($exception->payload()['code'] ?? null)->toBe('remote_fault');
    }
});

it('reports unknown functions', function (): void {
    TestCase::api([]);

    expect(fn() => ISPConfig::call('does_not_exist'))->toThrow(ApiException::class, 'Method does_not_exist does not exist');
});

it('retries read functions after a connection error', function () use ($client): void {
    $attempts = 0;
    TestCase::api(['client_get' => function () use (&$attempts, $client) {
        if (++$attempts === 1) {
            throw new ConnectionException('reset');
        }

        return Http::response(TestCase::ok($client));
    }]);

    expect(ISPConfig::clients()->get(1))->not->toBeNull()
        ->and($attempts)->toBe(2);
});

it('retries read functions after a server error', function () use ($client): void {
    TestCase::api(['client_get' => [fn() => Http::response('Bad gateway', 502), TestCase::ok($client)]]);

    expect(ISPConfig::clients()->get(1)?->companyName)->toBe('Shop Ltd.');
});

it('sends write functions exactly once', function (): void {
    $attempts = 0;
    TestCase::api(['client_add' => function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timed out');
    }]);

    expect(fn() => ISPConfig::clients()->create(['username' => 'x']))->toThrow(ApiException::class, 'Could not connect to ISPConfig for client_add: timed out')
        ->and($attempts)->toBe(1);
});

it('reports rate limiting with the retry delay', function (): void {
    TestCase::api(['client_get' => fn() => Http::response('', 429, ['Retry-After' => '30'])]);

    try {
        ISPConfig::clients()->get(1);
        test()->fail('Expected exception was not thrown.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter())->toBe(30);
    }
});

it('reports answers that are not api json', function (): void {
    TestCase::api(['client_get' => fn() => Http::response('<html>Remote API is disabled</html>', 200)]);

    expect(fn() => ISPConfig::clients()->get(1))->toThrow(ApiException::class, 'unexpected response');
});

it('ends the session with logout', function () use ($client): void {
    TestCase::api(['client_get' => TestCase::ok($client)]);

    ISPConfig::clients()->get(1);

    expect(ISPConfig::logout())->toBeTrue()
        ->and(ISPConfig::logout())->toBeFalse();

    Http::assertSent(fn(Request $request): bool => TestCase::functionOf($request) === 'logout'
        && $request->data() === ['session_id' => TestCase::SESSION]);
});

it('uses named and runtime connections with their own sessions', function (): void {
    TestCase::api(['server_get_all' => TestCase::ok([['server_id' => '1', 'server_name' => 'web1.example.com']])]);

    ISPConfig::server()->all();
    $servers = ISPConfig::connection('backup')->server()->all();
    ISPConfig::using(['url' => 'https://other.example.com:9090', 'username' => 'u', 'password' => 'p'])->server()->all();

    expect($servers[0]->name)->toBe('web1.example.com');

    Http::assertSent(fn(Request $request): bool => $request->url() === 'http://10.0.0.5:8081/remote/json.php?login'
        && ($request->data()['username'] ?? null) === 'remote2');
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://other.example.com:9090/remote/json.php?server_get_all');
    expect(Http::recorded(fn(Request $request): bool => TestCase::functionOf($request) === 'login'))->toHaveCount(3);
});

it('fails for unknown connections', function (): void {
    expect(fn() => ISPConfig::connection('missing'))->toThrow(InvalidConfiguration::class, 'missing');
});

it('runs the check command', function (): void {
    TestCase::api([
        'server_get_app_version' => TestCase::ok(['ispc_app_version' => '3.3.3p2']),
        'server_get_all' => TestCase::ok([['server_id' => '1', 'server_name' => 'web1.example.com']]),
    ]);

    $this->artisan('ispconfig:check')
        ->expectsOutputToContain('Logged in to ' . TestCase::URL . ' as remote')
        ->assertSuccessful();

    TestCase::refake(['login' => TestCase::fault('The login failed. Username or password wrong.')]);

    $this->artisan('ispconfig:check', ['connection' => 'backup'])->assertFailed();
});
