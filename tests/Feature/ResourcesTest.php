<?php

declare(strict_types=1);

use Beziworld\ISPConfig\Data\Website;
use Beziworld\ISPConfig\Facades\ISPConfig;
use Beziworld\ISPConfig\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Asserts that a remote function was called with the given parameters (session_id excluded).
 *
 * @param array<string, mixed> $parameters
 */
function assertCalled(string $function, array $parameters): void
{
    Http::assertSent(static function (Request $request) use ($function, $parameters): bool {
        if (TestCase::functionOf($request) !== $function) {
            return false;
        }

        $data = $request->data();

        if (($data['session_id'] ?? null) !== TestCase::SESSION) {
            return false;
        }

        unset($data['session_id']);

        return $data === $parameters;
    });
}

$website = [
    'domain_id' => '12', 'server_id' => '1', 'domain' => 'shop.example.com', 'type' => 'vhost', 'parent_domain_id' => '0',
    'document_root' => '/var/www/clients/client3/web12', 'system_user' => 'web12', 'system_group' => 'client3',
    'php' => 'php-fpm', 'hd_quota' => '1024', 'traffic_quota' => '-1', 'ssl' => 'y', 'ssl_letsencrypt' => 'y', 'active' => 'y',
];

it('creates and updates clients', function (): void {
    TestCase::api(['client_add' => TestCase::ok(3), 'client_update' => TestCase::ok(1), 'client_change_password' => TestCase::ok(true)]);

    $params = ['company_name' => 'Shop Ltd.', 'contact_name' => 'Doe', 'email' => 'office@shop.example', 'username' => 'shop', 'password' => 'Secret-123', 'limit_web_domain' => 5];

    expect(ISPConfig::clients()->create($params))->toBe(3)
        ->and(ISPConfig::clients()->lock(3))->toBe(1)
        ->and(ISPConfig::clients()->changePassword(3, 'New-Secret-1'))->toBeTrue();

    assertCalled('client_add', ['reseller_id' => 0, 'params' => $params]);
    assertCalled('client_update', ['client_id' => 3, 'reseller_id' => 0, 'params' => ['locked' => 'y']]);
    assertCalled('client_change_password', ['client_id' => 3, 'new_password' => 'New-Secret-1']);
});

it('searches clients with filters and reads helper ids', function (): void {
    TestCase::api([
        'client_get' => TestCase::ok([['client_id' => '3', 'username' => 'shop', 'limit_client' => '10']]),
        'client_get_all' => TestCase::ok(['1', '3']),
        'client_get_groupid' => TestCase::ok('4'),
    ]);

    $client = ISPConfig::clients()->findByUsername('shop');

    expect($client?->reseller)->toBeTrue()
        ->and(ISPConfig::clients()->ids())->toBe([1, 3])
        ->and(ISPConfig::clients()->groupId(3))->toBe(4);

    assertCalled('client_get', ['client_id' => ['username' => 'shop']]);
});

it('returns null for missing records', function (): void {
    TestCase::api(['client_get' => TestCase::ok([]), 'sites_web_domain_get' => TestCase::ok(false)]);

    expect(ISPConfig::clients()->get(99))->toBeNull()
        ->and(ISPConfig::sites()->get(99))->toBeNull();
});

it('manages websites', function () use ($website): void {
    TestCase::api([
        'sites_web_domain_get' => TestCase::ok([$website]),
        'sites_web_domain_add' => TestCase::ok('12'),
        'sites_web_domain_update' => TestCase::ok(1),
        'sites_web_domain_set_status' => TestCase::ok(0),
        'sites_web_aliasdomain_add' => TestCase::ok(13),
    ]);

    $sites = ISPConfig::sites()->all();

    expect($sites[0]->domain)->toBe('shop.example.com')
        ->and($sites[0]->letsEncrypt)->toBeTrue()
        ->and($sites[0]->diskQuotaMb)->toBe(1024)
        ->and(ISPConfig::sites()->create(3, ['server_id' => 1, 'domain' => 'shop.example.com', 'ip_address' => '*'], readonly: true))->toBe(12)
        ->and(ISPConfig::sites()->enableLetsEncrypt(3, 12))->toBe(1)
        ->and(ISPConfig::sites()->setActive(12, false))->toBeFalse()
        ->and(ISPConfig::sites()->createAliasDomain(3, 12, 'shop.example.net'))->toBe(13);

    assertCalled('sites_web_domain_get', ['primary_id' => -1]);
    assertCalled('sites_web_domain_add', [
        'client_id' => 3,
        'params' => ['server_id' => 1, 'domain' => 'shop.example.com', 'ip_address' => '*', 'type' => 'vhost', 'vhost_type' => 'name'],
        'readonly' => true,
    ]);
    assertCalled('sites_web_domain_update', ['client_id' => 3, 'primary_id' => 12, 'params' => ['ssl' => 'y', 'ssl_letsencrypt' => 'y']]);
    assertCalled('sites_web_domain_set_status', ['primary_id' => 12, 'status' => 'inactive']);
    assertCalled('sites_web_aliasdomain_add', ['client_id' => 3, 'params' => ['parent_domain_id' => 12, 'domain' => 'shop.example.net', 'active' => 'y']]);
});

it('reads website quota and traffic', function (): void {
    TestCase::api([
        'quota_get_by_user' => TestCase::ok([['domain' => 'shop.example.com', 'used_raw' => 1048576, 'hard_raw' => -1]]),
        'trafficquota_get_by_user' => TestCase::ok(['shop.example.com' => ['domain_id' => '12', 'this_month' => '1024']]),
    ]);

    expect(ISPConfig::sites()->quota(3)[0]['used_raw'])->toBe(1048576)
        ->and(ISPConfig::sites()->traffic(3, 7))->toHaveKey('shop.example.com');

    assertCalled('trafficquota_get_by_user', ['client_id' => 3, 'lastdays' => 7]);
});

it('manages the domain module', function (): void {
    TestCase::api(['domains_domain_add' => TestCase::ok(8), 'domains_get_all_by_user' => TestCase::ok([['domain_id' => '8', 'domain' => 'shop.example.com']])]);

    expect(ISPConfig::domains()->create(3, 'shop.example.com'))->toBe(8)
        ->and(ISPConfig::domains()->ofGroup(4)[0]['domain'])->toBe('shop.example.com');

    assertCalled('domains_domain_add', ['client_id' => 3, 'params' => ['domain' => 'shop.example.com']]);
    assertCalled('domains_get_all_by_user', ['group_id' => 4]);
});

it('creates zones and records', function (): void {
    TestCase::api([
        'dns_zone_add' => TestCase::ok(5),
        'dns_templatezone_add' => TestCase::ok(true),
        'dns_mx_add' => TestCase::ok(41),
        'dns_txt_update' => TestCase::ok(1),
        'dns_a_delete' => TestCase::ok(1),
    ]);

    $zone = ISPConfig::dns()->createZone(3, ['server_id' => 1, 'origin' => 'shop.example.com', 'ns' => 'ns1.example.com.', 'mbox' => 'hostmaster.shop.example.com.']);

    expect($zone)->toBe(5)
        ->and(ISPConfig::dns()->createZoneFromTemplate(3, 'shop.example.org.', '192.0.2.10', 'ns1.example.com', 'ns2.example.com', 'hostmaster@shop.example.org'))->toBeTrue()
        ->and(ISPConfig::dns()->createRecord(3, 5, 'MX', '@', 'mail.shop.example.com.', params: ['aux' => 10]))->toBe(41)
        ->and(ISPConfig::dns()->updateRecord(3, 'txt', 42, ['data' => 'v=spf1 mx -all'], updateSerial: false))->toBe(1)
        ->and(ISPConfig::dns()->deleteRecord('A', 43))->toBe(1);

    assertCalled('dns_zone_add', ['client_id' => 3, 'params' => ['server_id' => 1, 'origin' => 'shop.example.com.', 'ns' => 'ns1.example.com.', 'mbox' => 'hostmaster.shop.example.com.', 'active' => 'Y']]);
    assertCalled('dns_templatezone_add', [
        'client_id' => 3, 'template_id' => 1, 'domain' => 'shop.example.org', 'ip' => '192.0.2.10', 'ns1' => 'ns1.example.com',
        'ns2' => 'ns2.example.com', 'email' => 'hostmaster@shop.example.org', 'ipv6' => '',
    ]);
    assertCalled('dns_mx_add', [
        'client_id' => 3,
        'params' => ['zone' => 5, 'name' => '', 'data' => 'mail.shop.example.com.', 'ttl' => 3600, 'active' => 'Y', 'aux' => 10],
        'update_serial' => true,
    ]);
    assertCalled('dns_txt_update', ['client_id' => 3, 'primary_id' => 42, 'params' => ['data' => 'v=spf1 mx -all'], 'update_serial' => false]);
    assertCalled('dns_a_delete', ['primary_id' => 43, 'update_serial' => true]);
});

it('lists zone records and resolves zone ids', function (): void {
    TestCase::api([
        'dns_rr_get_all_by_zone' => TestCase::ok([
            ['id' => '41', 'zone' => '5', 'name' => '', 'type' => 'MX', 'data' => 'mail.shop.example.com.', 'aux' => '10', 'ttl' => '3600', 'active' => 'Y'],
            ['id' => '42', 'zone' => '5', 'name' => 'www', 'type' => 'A', 'data' => '192.0.2.10', 'aux' => '0', 'ttl' => '300', 'active' => 'N'],
        ]),
        'dns_zone_get_id' => [TestCase::ok('5'), TestCase::fault('There is no domain ID with informed domain name.')],
    ]);

    $records = ISPConfig::dns()->records(5);

    expect($records)->toHaveCount(2)
        ->and($records[0]->priority)->toBe(10)
        ->and($records[1]->active)->toBeFalse()
        ->and(ISPConfig::dns()->zoneId('shop.example.com.'))->toBe(5)
        ->and(ISPConfig::dns()->zoneId('unknown.example'))->toBeNull();

    assertCalled('dns_zone_get_id', ['origin' => 'shop.example.com']);
});

it('manages mail domains and mailboxes', function (): void {
    TestCase::api([
        'mail_domain_add' => TestCase::ok(6),
        'mail_domain_get_by_domain' => TestCase::ok([['domain_id' => '6', 'server_id' => '1', 'domain' => 'shop.example.com', 'dkim' => 'y', 'dkim_selector' => 'default', 'active' => 'y']]),
        'mail_user_add' => TestCase::ok(17),
        'mail_user_update' => TestCase::ok(1),
        'mail_user_get_all_by_client' => TestCase::ok([['mailuser_id' => '17', 'email' => 'office@shop.example.com', 'quota' => '2147483648']]),
    ]);

    expect(ISPConfig::email()->createDomain(3, 1, 'shop.example.com', ['dkim' => 'n']))->toBe(6)
        ->and(ISPConfig::email()->findDomain('shop.example.com')?->dkimSelector)->toBe('default')
        ->and(ISPConfig::email()->createMailbox(3, 1, 'office@shop.example.com', 'Mail-Secret-1', 2048, ['name' => 'Office']))->toBe(17)
        ->and(ISPConfig::email()->changeMailboxQuota(3, 17, 4096))->toBe(1)
        ->and(ISPConfig::email()->mailboxesOfClient(3)[0]->quotaBytes)->toBe(2147483648);

    assertCalled('mail_domain_add', ['client_id' => 3, 'params' => ['server_id' => 1, 'domain' => 'shop.example.com', 'active' => 'y', 'dkim' => 'n']]);
    assertCalled('mail_user_add', ['client_id' => 3, 'params' => [
        'server_id' => 1, 'email' => 'office@shop.example.com', 'login' => 'office@shop.example.com', 'password' => 'Mail-Secret-1',
        'quota' => 2147483648, 'postfix' => 'y', 'name' => 'Office',
    ]]);
    assertCalled('mail_user_update', ['client_id' => 3, 'primary_id' => 17, 'params' => ['quota' => 4294967296]]);
});

it('creates aliases, forwards and catchalls', function (): void {
    TestCase::api(['mail_alias_add' => TestCase::ok(30), 'mail_forward_add' => TestCase::ok(31), 'mail_catchall_add' => TestCase::ok(32)]);

    ISPConfig::email()->createAlias(3, 1, 'sales@shop.example.com', 'office@shop.example.com');
    ISPConfig::email()->createForward(3, 1, 'boss@shop.example.com', ['ceo@example.net', 'assistant@example.net']);
    ISPConfig::email()->createCatchall(3, 1, 'shop.example.com', 'office@shop.example.com');

    assertCalled('mail_alias_add', ['client_id' => 3, 'params' => ['server_id' => 1, 'source' => 'sales@shop.example.com', 'destination' => 'office@shop.example.com', 'type' => 'alias', 'allow_send_as' => 'y', 'active' => 'y']]);
    assertCalled('mail_forward_add', ['client_id' => 3, 'params' => ['server_id' => 1, 'source' => 'boss@shop.example.com', 'destination' => 'ceo@example.net,assistant@example.net', 'type' => 'forward', 'allow_send_as' => 'n', 'active' => 'y']]);
    assertCalled('mail_catchall_add', ['client_id' => 3, 'params' => ['server_id' => 1, 'source' => '@shop.example.com', 'destination' => 'office@shop.example.com', 'type' => 'catchall', 'allow_send_as' => 'n', 'active' => 'y']]);
});

it('creates databases with their users', function (): void {
    TestCase::api([
        'sites_database_user_add' => TestCase::ok(9),
        'sites_database_add' => TestCase::ok(10),
        'sites_database_get_all_by_user' => TestCase::ok([['database_id' => '10', 'database_name' => 'c3shop', 'database_user_id' => '9', 'database_ro_user_id' => '0', 'database_user' => 'c3shop']]),
    ]);

    $user = ISPConfig::databases()->createUser(3, 'c3shop', 'Db-Secret-1');
    $database = ISPConfig::databases()->create(3, 1, 12, 'c3shop', $user, ['remote_access' => 'y', 'remote_ips' => '10.0.0.8']);

    expect($database)->toBe(10)
        ->and(ISPConfig::databases()->ofClient(3)[0]->userName)->toBe('c3shop');

    assertCalled('sites_database_user_add', ['client_id' => 3, 'params' => ['server_id' => 0, 'database_user' => 'c3shop', 'database_password' => 'Db-Secret-1']]);
    assertCalled('sites_database_add', ['client_id' => 3, 'params' => [
        'server_id' => 1, 'parent_domain_id' => 12, 'type' => 'mysql', 'database_name' => 'c3shop', 'database_user_id' => 9,
        'database_ro_user_id' => 0, 'database_charset' => 'utf8mb4', 'remote_access' => 'y', 'active' => 'y', 'remote_ips' => '10.0.0.8',
    ]]);
});

it('manages cron jobs', function (): void {
    TestCase::api(['sites_cron_add' => TestCase::ok(21), 'sites_cron_update' => TestCase::ok(1), 'sites_cron_delete' => TestCase::ok(1)]);

    expect(ISPConfig::cron()->create(3, 1, 12, '* * * * *', 'https://shop.example.com/schedule'))->toBe(21)
        ->and(ISPConfig::cron()->update(3, 21, ['schedule' => '*/5 * * * *']))->toBe(1)
        ->and(ISPConfig::cron()->delete(21))->toBe(1);

    assertCalled('sites_cron_add', ['client_id' => 3, 'params' => [
        'server_id' => 1, 'parent_domain_id' => 12, 'run_min' => '*', 'run_hour' => '*', 'run_mday' => '*', 'run_month' => '*',
        'run_wday' => '*', 'type' => 'url', 'command' => 'https://shop.example.com/schedule', 'log' => 'n', 'active' => 'y',
    ]]);
    assertCalled('sites_cron_update', ['client_id' => 3, 'cron_id' => 21, 'params' => ['run_min' => '*/5', 'run_hour' => '*', 'run_mday' => '*', 'run_month' => '*', 'run_wday' => '*']]);
    assertCalled('sites_cron_delete', ['cron_id' => 21]);
});

it('creates ftp users from the website record', function () use ($website): void {
    TestCase::api(['sites_ftp_user_add' => TestCase::ok(14)]);

    expect(ISPConfig::ftp()->create(3, Website::fromArray($website), 'shopdeploy', 'Ftp-Secret-1', quotaMb: 500))->toBe(14)
        ->and(fn() => ISPConfig::ftp()->create(3, Website::fromArray(['domain_id' => 1]), 'x', 'y'))->toThrow(InvalidArgumentException::class);

    assertCalled('sites_ftp_user_add', ['client_id' => 3, 'params' => [
        'server_id' => 1, 'parent_domain_id' => 12, 'username' => 'shopdeploy', 'password' => 'Ftp-Secret-1', 'quota_size' => 500,
        'uid' => 'web12', 'gid' => 'client3', 'dir' => '/var/www/clients/client3/web12', 'active' => 'y',
    ]]);
});

it('lists and queues backups', function (): void {
    TestCase::api([
        'sites_web_domain_backup_list' => TestCase::ok([['backup_id' => '55', 'server_id' => '1', 'parent_domain_id' => '12', 'backup_type' => 'web', 'backup_mode' => 'rootgz', 'tstamp' => '1794268800', 'filename' => 'web12_2026-11-10.tar.gz', 'filesize' => '1048576']]),
        'sites_web_domain_backup' => [TestCase::ok(true), TestCase::fault('There is already a pending backup_restore action')],
        'mail_user_backup' => TestCase::ok(true),
    ]);

    $backups = ISPConfig::backups()->ofWebsite(12);

    expect($backups[0]->createdAt?->toDateString())->toBe('2026-11-10')
        ->and($backups[0]->sizeBytes)->toBe(1048576)
        ->and(ISPConfig::backups()->restore(55))->toBeTrue()
        ->and(ISPConfig::backups()->restoreMailbox(70))->toBeTrue();

    try {
        ISPConfig::backups()->restore(55);
        test()->fail('Expected exception was not thrown.');
    } catch (Beziworld\ISPConfig\Exceptions\ApiException $exception) {
        expect($exception->faultCode())->toBe('duplicate_action');
    }

    assertCalled('sites_web_domain_backup_list', ['site_id' => 12]);
    assertCalled('sites_web_domain_backup', ['primary_id' => 55, 'action_type' => 'backup_restore']);
    assertCalled('mail_user_backup', ['primary_id' => 70, 'action_type' => 'backup_restore_mail']);
});

it('reads server information', function (): void {
    TestCase::api([
        'server_get_app_version' => TestCase::ok(['ispc_app_version' => '3.3.3p2']),
        'server_get_serverid_by_name' => TestCase::ok(['server_id' => '2']),
        'server_get_functions' => TestCase::ok([['mail_server' => '1', 'web_server' => '1', 'dns_server' => '0']]),
        'monitor_jobqueue_count' => TestCase::ok('3'),
        'get_function_list' => TestCase::ok(['login', 'logout', 'client_get']),
    ]);

    expect(ISPConfig::server()->version())->toBe('3.3.3p2')
        ->and(ISPConfig::server()->idByName('web2.example.com'))->toBe(2)
        ->and(ISPConfig::server()->functions(1)['web_server'])->toBe('1')
        ->and(ISPConfig::server()->pendingJobs())->toBe(3)
        ->and(ISPConfig::server()->functionList())->toContain('client_get');

    assertCalled('server_get_app_version', ['server_id' => 0]);
});

it('calls any remote function', function (): void {
    TestCase::api(['sites_shell_user_add' => TestCase::ok(77)]);

    expect(ISPConfig::call('sites_shell_user_add', ['client_id' => 3, 'params' => ['username' => 'deploy']]))->toBe(77);

    assertCalled('sites_shell_user_add', ['client_id' => 3, 'params' => ['username' => 'deploy']]);
});
