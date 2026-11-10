<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Tests;

use Beziworld\ISPConfig\Facades\ISPConfig;
use Beziworld\ISPConfig\ISPConfigManager;
use Beziworld\ISPConfig\ISPConfigServiceProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string URL = 'https://panel.example.com:8080/remote/json.php';

    public const string SESSION = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake();
    }

    protected function getPackageProviders($app): array
    {
        return [ISPConfigServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('ispconfig.connections.default', [
            'url' => 'panel.example.com',
            'username' => 'remote',
            'password' => 'remote-secret',
        ]);
        $app['config']->set('ispconfig.connections.backup', [
            'url' => 'http://10.0.0.5:8081/remote',
            'username' => 'remote2',
            'password' => 'other-secret',
            'verify_ssl' => 'false',
        ]);
    }

    /**
     * JSON answer of the remote API.
     *
     * @return array{code: string, message: string, response: mixed}
     */
    public static function ok(mixed $response): array
    {
        return ['code' => 'ok', 'message' => '', 'response' => $response];
    }

    /**
     * @return array{code: string, message: string, response: false}
     */
    public static function fault(string $message, string $code = 'remote_fault'): array
    {
        return ['code' => $code, 'message' => $message, 'response' => false];
    }

    /**
     * Fakes the login and the given function answers, keyed by remote function name.
     *
     * @param array<string, mixed> $answers
     */
    public static function api(array $answers): void
    {
        $answers += ['login' => self::ok(self::SESSION), 'logout' => self::ok(true)];

        Http::fake(static function (Request $request) use (&$answers) {
            $function = self::functionOf($request);
            $answer = $answers[$function] ?? self::fault('Method ' . $function . ' does not exist', 'invalid_method');

            // A list of answers is returned one after another, the last one repeatedly.
            if (is_array($answer) && array_is_list($answer) && $answer !== [] && (is_array($answer[0]) || $answer[0] instanceof \Closure)) {
                $answer = count($answers[$function]) > 1 ? array_shift($answers[$function]) : $answer[0];
            }

            return $answer instanceof \Closure ? $answer($request) : Http::response($answer);
        });
    }

    /**
     * Replaces all previously registered HTTP stubs instead of appending to them.
     *
     * @param array<string, mixed> $answers
     */
    public static function refake(array $answers): void
    {
        Http::swap(new Factory(app('events')));
        Http::preventStrayRequests();
        app()->forgetInstance(ISPConfigManager::class);
        ISPConfig::clearResolvedInstances();
        self::api($answers);
    }

    /**
     * Name of the remote function of a recorded request.
     */
    public static function functionOf(Request $request): string
    {
        return (string) parse_url($request->url(), PHP_URL_QUERY);
    }
}
