<?php

declare(strict_types=1);

use Beziworld\ISPConfig\ConnectionConfig;
use Beziworld\ISPConfig\Exceptions\InvalidConfiguration;
use Beziworld\ISPConfig\Support\Values;

it('converts loosely typed scalars', function (): void {
    $data = ['text' => 'abc', 'number' => '42', 'float' => '1.5', 'empty' => '', 'list' => ['a', 1, ['nested'], null], 'flag' => true];

    expect(Values::string($data, 'number'))->toBe('42')
        ->and(Values::string($data, 'list', 'fallback'))->toBe('fallback')
        ->and(Values::nullableString($data, 'empty'))->toBeNull()
        ->and(Values::int($data, 'text', 7))->toBe(7)
        ->and(Values::nullableInt($data, 'missing'))->toBeNull()
        ->and(Values::nullableFloat($data, 'float'))->toBe(1.5)
        ->and(Values::nullableFloat($data, 'text'))->toBeNull()
        ->and(Values::bool($data, 'flag'))->toBeTrue()
        ->and(Values::bool(['v' => 'Yes'], 'v'))->toBeTrue()
        ->and(Values::bool(['v' => 'n'], 'v'))->toBeFalse()
        ->and(Values::bool(['v' => ['y']], 'v'))->toBeFalse()
        ->and(Values::strings($data, 'list'))->toBe(['a', '1'])
        ->and(Values::strings($data, 'text'))->toBe([]);
});

it('keeps only array rows', function (): void {
    expect(Values::rows([['a' => 1], 'x', ['b' => 2]]))->toBe([['a' => 1], ['b' => 2]])
        ->and(Values::rows('x'))->toBe([]);
});

it('parses timestamps and dates', function (): void {
    expect(Values::date(['d' => 1762770000], 'd')?->getTimestamp())->toBe(1762770000)
        ->and(Values::date(['d' => 0], 'd'))->toBeNull()
        ->and(Values::date(['d' => '0000-00-00 00:00:00'], 'd'))->toBeNull()
        ->and(Values::date(['d' => '   '], 'd'))->toBeNull()
        ->and(Values::date(['d' => 'not a date at all'], 'd'))->toBeNull()
        ->and(Values::date(['d' => '2026-11-10 10:00:00'], 'd', 'Europe/Warsaw')?->toIso8601String())->toBe('2026-11-10T10:00:00+01:00');
});

it('formats y and n flags', function (): void {
    expect(Values::yesNo(true))->toBe('y')
        ->and(Values::yesNo(false))->toBe('n')
        ->and(Values::yesNo(true, upper: true))->toBe('Y');
});

it('reads timeouts, the cache store and non string ssl settings', function (): void {
    $config = ConnectionConfig::fromArray('panel2', [
        'url' => ' https://panel2.example.com ',
        'username' => 'remote',
        'password' => 'secret',
        'verify_ssl' => 0,
        'timeout' => '30',
        'connect_timeout' => -5,
        'cache_store' => 'redis',
    ]);

    expect($config->url)->toBe('https://panel2.example.com:8080/remote/json.php')
        ->and($config->verifySsl)->toBeTrue()
        ->and($config->timeout)->toBe(30)
        ->and($config->connectTimeout)->toBe(10)
        ->and($config->cacheStore)->toBe('redis')
        ->and($config->sessionCacheKey())->toStartWith('ispconfig:session:')
        ->and($config->sessionCacheKey())->not->toBe(ConnectionConfig::fromArray('x', ['url' => 'p', 'username' => 'remote', 'password' => 'other'])->sessionCacheKey());
});

it('names the environment variable only for the default connection', function (): void {
    expect(InvalidConfiguration::missing('default', 'url', 'ISPCONFIG_URL')->getMessage())->toBe('ISPConfig connection "default" has no "url" configured (set ISPCONFIG_URL).')
        ->and(InvalidConfiguration::missing('panel2', 'url', 'ISPCONFIG_URL')->getMessage())->toBe('ISPConfig connection "panel2" has no "url" configured.');
});
