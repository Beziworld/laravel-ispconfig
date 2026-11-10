<?php

declare(strict_types=1);

use Beziworld\ISPConfig\Connection;
use Beziworld\ISPConfig\ConnectionConfig;
use Beziworld\ISPConfig\Data\CronJob;
use Beziworld\ISPConfig\Data\DnsRecord;
use Beziworld\ISPConfig\Data\Mailbox;
use Beziworld\ISPConfig\Data\MailForwarding;
use Beziworld\ISPConfig\Exceptions\ApiException;
use Beziworld\ISPConfig\Exceptions\InvalidConfiguration;
use Beziworld\ISPConfig\Resources\Cron;
use Beziworld\ISPConfig\Resources\Dns;

it('completes the panel url to the json endpoint', function (string $url, string $expected): void {
    expect(ConnectionConfig::normalizeUrl($url))->toBe($expected);
})->with([
    ['panel.example.com', 'https://panel.example.com:8080/remote/json.php'],
    ['https://panel.example.com/', 'https://panel.example.com:8080/remote/json.php'],
    ['https://panel.example.com:8443', 'https://panel.example.com:8443/remote/json.php'],
    ['http://10.0.0.5:8081/remote', 'http://10.0.0.5:8081/remote/json.php'],
    ['https://panel.example.com:8080/remote/json.php', 'https://panel.example.com:8080/remote/json.php'],
]);

it('requires url and credentials', function (array $config, string $key): void {
    expect(fn() => ConnectionConfig::fromArray('default', $config))->toThrow(InvalidConfiguration::class, $key);
})->with([
    [[], 'ISPCONFIG_URL'],
    [['url' => 'p'], 'ISPCONFIG_USERNAME'],
    [['url' => 'p', 'username' => 'u'], 'ISPCONFIG_PASSWORD'],
]);

it('keeps the session ttl below the server limit', function (): void {
    $config = ConnectionConfig::fromArray('x', ['url' => 'p', 'username' => 'u', 'password' => 's', 'session_ttl' => 7200]);

    expect($config->sessionTtl)->toBe(1740)
        ->and(ConnectionConfig::fromArray('x', ['url' => 'p', 'username' => 'u', 'password' => 's'])->sessionTtl)->toBe(1500);
});

it('parses the ssl verification setting', function (mixed $value, bool|string $expected): void {
    expect(ConnectionConfig::fromArray('x', ['url' => 'p', 'username' => 'u', 'password' => 's', 'verify_ssl' => $value])->verifySsl)->toBe($expected);
})->with([
    [false, false],
    ['false', false],
    ['1', true],
    ['/etc/ssl/ca.pem', '/etc/ssl/ca.pem'],
]);

it('classifies read-only functions', function (string $function, bool $readOnly): void {
    expect(Connection::isReadOnly($function))->toBe($readOnly);
})->with([
    ['client_get', true],
    ['sites_web_domain_get', true],
    ['mail_user_get_all_by_client', true],
    ['sites_web_domain_backup_list', true],
    ['monitor_jobqueue_count', true],
    ['get_function_list', true],
    ['client_add', false],
    ['sites_web_domain_backup', false],
    ['dns_a_delete', false],
    ['login', false],
]);

it('restores fault codes from fault strings', function (string $message, ?string $code): void {
    expect(ApiException::faultFromMessage($message))->toBe($code);
})->with([
    ['The Session is expired or does not exist.', 'session_does_not_exist'],
    ['You do not have the permissions to access this function.', 'permission_denied'],
    ['Invalid value for the field server_php_id.', 'data_processing_error'],
    ['There is already a pending backup_restore action', 'duplicate_action'],
    ['Something unexpected', null],
]);

it('maps dns record types to function names', function (): void {
    expect(Dns::function('mx', 'add'))->toBe('dns_mx_add')
        ->and(Dns::function('TXT', 'delete'))->toBe('dns_txt_delete')
        ->and(fn() => Dns::function('SPF', 'add'))->toThrow(InvalidArgumentException::class, 'SPF');
});

it('splits cron expressions', function (): void {
    expect(Cron::schedule('30 3 * * 1-5'))->toBe(['run_min' => '30', 'run_hour' => '3', 'run_mday' => '*', 'run_month' => '*', 'run_wday' => '1-5'])
        ->and(Cron::schedule('@reboot')['run_month'])->toBe('@reboot')
        ->and(fn() => Cron::schedule('* * *'))->toThrow(InvalidArgumentException::class);
});

it('maps records into data objects', function (): void {
    $record = DnsRecord::fromArray(['id' => '7', 'zone' => '2', 'name' => 'www', 'type' => 'a', 'data' => '192.0.2.10', 'aux' => '0', 'ttl' => '3600', 'active' => 'Y', 'stamp' => '2026-11-07 10:00:00'], 'Europe/Berlin');
    $mailbox = Mailbox::fromArray(['mailuser_id' => '3', 'email' => 'info@example.com', 'quota' => '0', 'disableimap' => 'y', 'postfix' => 'y']);
    $forward = MailForwarding::fromArray(['forwarding_id' => '4', 'source' => 'a@example.com', 'destination' => "x@example.net, y@example.net\nz@example.net", 'type' => 'forward']);
    $cron = CronJob::fromArray(['id' => '9', 'run_min' => '0', 'run_hour' => '*/2', 'command' => 'https://example.com/cron', 'active' => 'y']);

    expect($record->type)->toBe('A')
        ->and($record->active)->toBeTrue()
        ->and($record->changedAt?->timezone->getName())->toBe('Europe/Berlin')
        ->and($mailbox->login)->toBe('info@example.com')
        ->and($mailbox->imap)->toBeFalse()
        ->and($mailbox->unlimited())->toBeTrue()
        ->and($forward->destinations)->toBe(['x@example.net', 'y@example.net', 'z@example.net'])
        ->and($cron->schedule)->toBe('0 */2 * * *');
});
