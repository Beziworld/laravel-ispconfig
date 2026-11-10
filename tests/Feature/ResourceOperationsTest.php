<?php

declare(strict_types=1);

use Beziworld\ISPConfig\Exceptions\ApiException;
use Beziworld\ISPConfig\Facades\ISPConfig;
use Beziworld\ISPConfig\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Asserts that a remote function was sent once with exactly the given parameters (session_id excluded).
 *
 * @param array<string, mixed> $parameters
 */
function assertRemoteCall(string $function, array $parameters): void
{
    $matching = Http::recorded(static function (Request $request) use ($function, $parameters): bool {
        if (TestCase::functionOf($request) !== $function || $request->method() !== 'POST') {
            return false;
        }

        $data = $request->data();

        if (($data['session_id'] ?? null) !== TestCase::SESSION) {
            return false;
        }

        unset($data['session_id']);

        return $data === $parameters;
    });

    expect($matching)->toHaveCount(1, sprintf('Expected one %s call with the given parameters.', $function));
}

it('reads panel users, customer numbers and client helper ids', function (): void {
    TestCase::api([
        'client_get_by_username' => [TestCase::ok(['userid' => '5', 'client_id' => '3', 'default_group' => '4']), TestCase::ok([])],
        'client_get_by_customer_no' => TestCase::ok(['client_id' => '3', 'username' => 'shop', 'customer_no' => 'C-1003']),
        'client_get_id' => [TestCase::ok('3'), TestCase::ok(false)],
        'client_get_groupid' => TestCase::ok(0),
    ]);

    $clients = ISPConfig::clients();

    expect($clients->panelUser('shop'))->toBe(['userid' => '5', 'client_id' => '3', 'default_group' => '4'])
        ->and($clients->panelUser('nobody'))->toBeNull()
        ->and($clients->findByCustomerNo('C-1003')?->customerNo)->toBe('C-1003')
        ->and($clients->idOfPanelUser(5))->toBe(3)
        ->and($clients->idOfPanelUser(6))->toBeNull()
        ->and($clients->groupId(99))->toBeNull();

    assertRemoteCall('client_get_by_customer_no', ['customer_no' => 'C-1003']);
    assertRemoteCall('client_get_id', ['sys_userid' => 5]);
    assertRemoteCall('client_get_id', ['sys_userid' => 6]);
});

it('deletes clients and manages their templates', function (): void {
    TestCase::api([
        'client_update' => TestCase::ok(true),
        'client_delete' => TestCase::ok(1),
        'client_delete_everything' => TestCase::ok('1'),
        'client_templates_get_all' => TestCase::ok([['template_id' => '1', 'template_name' => 'Basic'], ['template_id' => '2', 'template_name' => 'Pro']]),
        'client_template_additional_add' => TestCase::ok(11),
        'client_template_additional_delete' => TestCase::ok(null),
    ]);

    $clients = ISPConfig::clients();

    expect($clients->lock(3, false))->toBe(1)
        ->and($clients->update(3, ['email' => 'new@shop.example'], resellerId: 2))->toBe(1)
        ->and($clients->delete(3))->toBe(1)
        ->and($clients->deleteEverything(3))->toBe(1)
        ->and($clients->templates())->toHaveCount(2)
        ->and($clients->templates()[1]['template_name'])->toBe('Pro')
        ->and($clients->addTemplate(3, 2))->toBe(11)
        ->and($clients->removeTemplate(3, 11))->toBe(0);

    assertRemoteCall('client_update', ['client_id' => 3, 'reseller_id' => 0, 'params' => ['locked' => 'n']]);
    assertRemoteCall('client_update', ['client_id' => 3, 'reseller_id' => 2, 'params' => ['email' => 'new@shop.example']]);
    assertRemoteCall('client_delete', ['client_id' => 3]);
    assertRemoteCall('client_delete_everything', ['client_id' => 3]);
    assertRemoteCall('client_template_additional_add', ['client_id' => 3, 'template_id' => 2]);
    assertRemoteCall('client_template_additional_delete', ['client_id' => 3, 'assigned_template_id' => 11]);
});

it('lists the websites of a panel user and checks logins', function (): void {
    TestCase::api([
        'client_get_sites_by_user' => TestCase::ok([['domain_id' => '12', 'domain' => 'shop.example.com', 'active' => 'y']]),
        'client_login_get' => [TestCase::ok(['type' => 'user', 'username' => 'shop']), TestCase::ok(false)],
        'client_get' => TestCase::ok([['client_id' => '3', 'company_name' => 'Shop Ltd.'], ['client_id' => '4', 'company_name' => 'Shop GmbH']]),
    ]);

    $clients = ISPConfig::clients();

    expect($clients->websites(5, '4,7')[0]->domain)->toBe('shop.example.com')
        ->and($clients->checkLogin('shop', 'Secret-123', '192.0.2.1'))->toBe(['type' => 'user', 'username' => 'shop'])
        ->and($clients->checkLogin('shop', 'wrong'))->toBeNull()
        ->and(array_map(static fn($client): int => $client->id, $clients->all(['company_name' => 'Shop%'])))->toBe([3, 4]);

    assertRemoteCall('client_get_sites_by_user', ['sys_userid' => 5, 'sys_groupid' => '4,7']);
    assertRemoteCall('client_login_get', ['username' => 'shop', 'password' => 'Secret-123', 'remote_ip' => '192.0.2.1']);
    assertRemoteCall('client_login_get', ['username' => 'shop', 'password' => 'wrong', 'remote_ip' => '']);
    assertRemoteCall('client_get', ['client_id' => ['company_name' => 'Shop%']]);
});

it('manages subdomains, alias domains and website status', function (): void {
    TestCase::api([
        'sites_web_domain_get' => TestCase::ok(['domain_id' => '12', 'domain' => 'shop.example.com', 'server_id' => '1']),
        'sites_web_domain_delete' => TestCase::ok(1),
        'sites_web_domain_set_status' => TestCase::ok(1),
        'sites_web_aliasdomain_delete' => TestCase::ok(1),
        'sites_web_subdomain_add' => TestCase::ok(15),
        'sites_web_subdomain_delete' => TestCase::ok(1),
        'sites_web_vhost_subdomain_add' => TestCase::ok('16'),
        'sites_web_vhost_subdomain_delete' => TestCase::ok(1),
    ]);

    $sites = ISPConfig::sites();

    expect($sites->findByDomain('shop.example.com')?->id)->toBe(12)
        ->and($sites->delete(12))->toBe(1)
        ->and($sites->setActive(12, true))->toBeTrue()
        ->and($sites->deleteAliasDomain(13))->toBe(1)
        ->and($sites->createSubdomain(3, 12, 'blog.shop.example.com', ['redirect_type' => 'R']))->toBe(15)
        ->and($sites->deleteSubdomain(15))->toBe(1)
        ->and($sites->createVhostSubdomain(3, ['parent_domain_id' => 12, 'domain' => 'api.shop.example.com']))->toBe(16)
        ->and($sites->deleteVhostSubdomain(16))->toBe(1);

    assertRemoteCall('sites_web_domain_get', ['primary_id' => ['domain' => 'shop.example.com']]);
    assertRemoteCall('sites_web_domain_delete', ['primary_id' => 12]);
    assertRemoteCall('sites_web_domain_set_status', ['primary_id' => 12, 'status' => 'active']);
    assertRemoteCall('sites_web_aliasdomain_delete', ['primary_id' => 13]);
    assertRemoteCall('sites_web_subdomain_add', ['client_id' => 3, 'params' => ['parent_domain_id' => 12, 'domain' => 'blog.shop.example.com', 'active' => 'y', 'redirect_type' => 'R']]);
    assertRemoteCall('sites_web_subdomain_delete', ['primary_id' => 15]);
    assertRemoteCall('sites_web_vhost_subdomain_add', ['client_id' => 3, 'params' => ['parent_domain_id' => 12, 'domain' => 'api.shop.example.com']]);
    assertRemoteCall('sites_web_vhost_subdomain_delete', ['primary_id' => 16]);
});

it('reads, updates and deletes domain module records', function (): void {
    TestCase::api([
        'domains_domain_get' => [TestCase::ok(['domain_id' => '8', 'domain' => 'shop.example.com']), TestCase::ok([['domain_id' => '8'], ['domain_id' => '9']])],
        'domains_domain_update' => TestCase::ok(1),
        'domains_domain_delete' => TestCase::ok(1),
    ]);

    $domains = ISPConfig::domains();

    expect($domains->get(8))->toBe(['domain_id' => '8', 'domain' => 'shop.example.com'])
        ->and($domains->all())->toHaveCount(2)
        ->and($domains->update(3, 8, ['domain' => 'shop.example.net']))->toBe(1)
        ->and($domains->delete(8))->toBe(1);

    assertRemoteCall('domains_domain_get', ['primary_id' => 8]);
    assertRemoteCall('domains_domain_get', ['primary_id' => -1]);
    assertRemoteCall('domains_domain_update', ['client_id' => 3, 'primary_id' => 8, 'params' => ['domain' => 'shop.example.net']]);
    assertRemoteCall('domains_domain_delete', ['primary_id' => 8]);
});

it('reads and changes dns zones', function (): void {
    $zone = ['id' => '5', 'server_id' => '1', 'origin' => 'shop.example.com.', 'ns' => 'ns1.example.com.', 'mbox' => 'hostmaster.shop.example.com.', 'serial' => '2026111001', 'ttl' => '3600', 'active' => 'Y', 'dnssec_wanted' => 'N'];

    TestCase::api([
        'dns_zone_get' => TestCase::ok($zone),
        'dns_zone_get_by_user' => TestCase::ok([['id' => '5', 'origin' => 'shop.example.com.']]),
        'dns_templatezone_get_all' => TestCase::ok([['template_id' => '1', 'name' => 'Default']]),
        'dns_zone_update' => TestCase::ok(1),
        'dns_zone_delete' => TestCase::ok(1),
        'dns_zone_set_status' => TestCase::ok(0),
        'dns_a_get' => TestCase::ok(['id' => '43', 'zone' => '5', 'name' => 'www', 'type' => 'A', 'data' => '192.0.2.10']),
    ]);

    $dns = ISPConfig::dns();
    $found = $dns->zone(5);

    expect($found?->serial)->toBe(2026111001)
        ->and($found?->domain())->toBe('shop.example.com')
        ->and($found?->dnssec)->toBeFalse()
        ->and($dns->findZone('shop.example.com')?->id)->toBe(5)
        ->and($dns->zonesOfClient(3, 1)[0]['origin'])->toBe('shop.example.com.')
        ->and($dns->templates()[0]['name'])->toBe('Default')
        ->and($dns->updateZone(3, 5, ['ttl' => 300]))->toBe(1)
        ->and($dns->deleteZone(5))->toBe(1)
        ->and($dns->setZoneActive(5, false))->toBeFalse()
        ->and($dns->record('a', 43)?->data)->toBe('192.0.2.10');

    assertRemoteCall('dns_zone_get', ['primary_id' => ['origin' => 'shop.example.com.']]);
    assertRemoteCall('dns_zone_get_by_user', ['client_id' => 3, 'server_id' => 1]);
    assertRemoteCall('dns_zone_update', ['client_id' => 3, 'primary_id' => 5, 'params' => ['ttl' => 300]]);
    assertRemoteCall('dns_zone_set_status', ['primary_id' => 5, 'status' => 'inactive']);
    assertRemoteCall('dns_a_get', ['primary_id' => 43]);
});

it('rethrows zone id faults other than a missing domain', function (): void {
    TestCase::api(['dns_zone_get_id' => TestCase::fault('You do not have the permissions to access this function.')]);

    expect(fn() => ISPConfig::dns()->zoneId('shop.example.com'))->toThrow(ApiException::class, 'permission_denied');
});

it('reads and changes mail domains and mailboxes', function (): void {
    TestCase::api([
        'mail_domain_get' => TestCase::ok([['domain_id' => '6', 'domain' => 'shop.example.com', 'dkim' => 'y', 'dkim_public' => 'MIIBIjAN', 'active' => 'y']]),
        'mail_domain_update' => TestCase::ok(1),
        'mail_domain_delete' => TestCase::ok(1),
        'mail_domain_set_status' => TestCase::ok(1),
        'mail_user_get' => TestCase::ok(['mailuser_id' => '17', 'email' => 'office@shop.example.com', 'login' => 'office', 'name' => 'Office', 'quota' => '1048576', 'postfix' => 'n', 'disablepop3' => 'y', 'autoresponder' => 'y']),
        'mail_user_update' => TestCase::ok(1),
        'mail_user_delete' => TestCase::ok(1),
        'mailquota_get_by_user' => TestCase::ok([['email' => 'office@shop.example.com', 'used_raw' => 1024, 'used_percentage' => 1]]),
    ]);

    $email = ISPConfig::email();
    $mailbox = $email->mailbox(17);

    expect($email->domain(6))->toBeNull()
        ->and($email->domains(['domain' => 'shop.example.com'])[0]->dkimPublicKey)->toBe('MIIBIjAN')
        ->and($email->updateDomain(3, 6, ['dkim' => 'n']))->toBe(1)
        ->and($email->deleteDomain(6))->toBe(1)
        ->and($email->setDomainActive(6, true))->toBeTrue()
        ->and($mailbox?->login)->toBe('office')
        ->and($mailbox?->receivesMail)->toBeFalse()
        ->and($mailbox?->pop3)->toBeFalse()
        ->and($mailbox?->autoresponder)->toBeTrue()
        ->and($mailbox?->unlimited())->toBeFalse()
        ->and($email->findMailbox('office@shop.example.com')?->id)->toBe(17)
        ->and($email->changeMailboxPassword(3, 17, 'Mail-Secret-2'))->toBe(1)
        ->and($email->deleteMailbox(17))->toBe(1)
        ->and($email->quota(3)[0]['used_raw'])->toBe(1024);

    assertRemoteCall('mail_domain_get', ['primary_id' => 6]);
    assertRemoteCall('mail_domain_get', ['primary_id' => ['domain' => 'shop.example.com']]);
    assertRemoteCall('mail_domain_update', ['client_id' => 3, 'primary_id' => 6, 'params' => ['dkim' => 'n']]);
    assertRemoteCall('mail_domain_set_status', ['primary_id' => 6, 'status' => 'active']);
    assertRemoteCall('mail_user_get', ['primary_id' => ['email' => 'office@shop.example.com']]);
    assertRemoteCall('mail_user_update', ['client_id' => 3, 'primary_id' => 17, 'params' => ['password' => 'Mail-Secret-2']]);
    assertRemoteCall('mail_user_delete', ['primary_id' => 17]);
    assertRemoteCall('mailquota_get_by_user', ['client_id' => 3]);
});

it('lists and changes aliases, forwards and catchalls', function (): void {
    TestCase::api([
        'mail_alias_get' => TestCase::ok([['forwarding_id' => '30', 'source' => 'sales@shop.example.com', 'destination' => 'office@shop.example.com', 'type' => 'alias', 'allow_send_as' => 'y', 'active' => 'y']]),
        'mail_forward_get' => TestCase::ok(['forwarding_id' => '31', 'source' => 'boss@shop.example.com', 'destination' => 'ceo@example.net,assistant@example.net', 'type' => 'forward']),
        'mail_catchall_get' => TestCase::ok([]),
        'mail_alias_update' => TestCase::ok(1),
        'mail_alias_delete' => TestCase::ok(1),
        'mail_forward_update' => TestCase::ok(1),
        'mail_forward_delete' => TestCase::ok(1),
        'mail_catchall_delete' => TestCase::ok(1),
    ]);

    $email = ISPConfig::email();
    $aliases = $email->aliases();
    $forwards = $email->forwards(['source' => 'boss@shop.example.com']);

    expect($aliases[0]->allowSendAs)->toBeTrue()
        ->and($forwards)->toHaveCount(1)
        ->and($forwards[0]->destinations)->toBe(['ceo@example.net', 'assistant@example.net'])
        ->and($email->catchalls())->toBe([])
        ->and($email->updateAlias(3, 30, ['active' => 'n']))->toBe(1)
        ->and($email->deleteAlias(30))->toBe(1)
        ->and($email->updateForward(3, 31, ['destination' => 'ceo@example.net']))->toBe(1)
        ->and($email->deleteForward(31))->toBe(1)
        ->and($email->deleteCatchall(32))->toBe(1);

    assertRemoteCall('mail_alias_get', ['primary_id' => -1]);
    assertRemoteCall('mail_forward_get', ['primary_id' => ['source' => 'boss@shop.example.com']]);
    assertRemoteCall('mail_alias_update', ['client_id' => 3, 'primary_id' => 30, 'params' => ['active' => 'n']]);
    assertRemoteCall('mail_forward_update', ['client_id' => 3, 'primary_id' => 31, 'params' => ['destination' => 'ceo@example.net']]);
    assertRemoteCall('mail_catchall_delete', ['primary_id' => 32]);
});

it('reads and changes databases and database users', function (): void {
    TestCase::api([
        'sites_database_get' => [
            TestCase::ok(['database_id' => '10', 'server_id' => '1', 'parent_domain_id' => '12', 'database_name' => 'c3shop', 'database_quota' => '500', 'remote_access' => 'y', 'active' => 'y']),
            TestCase::ok([['database_id' => '10'], ['database_id' => '11', 'type' => 'pgsql']]),
        ],
        'sites_database_update' => TestCase::ok(1),
        'sites_database_delete' => TestCase::ok(1),
        'sites_database_user_get' => [TestCase::ok(['database_user_id' => '9', 'server_id' => '0', 'database_user' => 'c3shop']), TestCase::ok([['database_user_id' => '9', 'database_user' => 'c3shop']])],
        'sites_database_user_update' => TestCase::ok(1),
        'sites_database_user_delete' => TestCase::ok(1),
        'databasequota_get_by_user' => TestCase::ok([['database_name' => 'c3shop', 'used' => '1048576']]),
    ]);

    $databases = ISPConfig::databases();
    $database = $databases->get(10);

    expect($database?->quotaMb)->toBe(500)
        ->and($database?->remoteAccess)->toBeTrue()
        ->and($database?->type)->toBe('mysql')
        ->and($databases->all()[1]->type)->toBe('pgsql')
        ->and($databases->update(3, 10, ['database_quota' => 1000]))->toBe(1)
        ->and($databases->delete(10))->toBe(1)
        ->and($databases->user(9)?->username)->toBe('c3shop')
        ->and($databases->users(['database_user' => 'c3shop'])[0]->id)->toBe(9)
        ->and($databases->changeUserPassword(3, 9, 'Db-Secret-2'))->toBe(1)
        ->and($databases->deleteUser(9))->toBe(1)
        ->and($databases->quota(3)[0]['used'])->toBe('1048576');

    assertRemoteCall('sites_database_update', ['client_id' => 3, 'primary_id' => 10, 'params' => ['database_quota' => 1000]]);
    assertRemoteCall('sites_database_user_get', ['primary_id' => ['database_user' => 'c3shop']]);
    assertRemoteCall('sites_database_user_update', ['client_id' => 3, 'primary_id' => 9, 'params' => ['database_password' => 'Db-Secret-2']]);
    assertRemoteCall('sites_database_user_delete', ['primary_id' => 9]);
    assertRemoteCall('databasequota_get_by_user', ['client_id' => 3]);
});

it('reads cron jobs and switches them on and off', function (): void {
    TestCase::api([
        'sites_cron_get' => [
            TestCase::ok(['id' => '21', 'parent_domain_id' => '12', 'type' => 'chrooted', 'command' => 'php artisan schedule:run', 'log' => 'y', 'active' => 'n']),
            TestCase::ok([['id' => '21'], ['id' => '22']]),
        ],
        'sites_cron_update' => TestCase::ok(1),
    ]);

    $cron = ISPConfig::cron();
    $job = $cron->get(21);

    expect($job?->type)->toBe('chrooted')
        ->and($job?->schedule)->toBe('* * * * *')
        ->and($job?->log)->toBeTrue()
        ->and($cron->all(['parent_domain_id' => 12]))->toHaveCount(2)
        ->and($cron->setActive(3, 21, true))->toBe(1);

    assertRemoteCall('sites_cron_get', ['cron_id' => 21]);
    assertRemoteCall('sites_cron_get', ['cron_id' => ['parent_domain_id' => 12]]);
    assertRemoteCall('sites_cron_update', ['client_id' => 3, 'cron_id' => 21, 'params' => ['active' => 'y']]);
});

it('reads and changes ftp users', function (): void {
    TestCase::api([
        'sites_ftp_user_get' => [
            TestCase::ok(['ftp_user_id' => '14', 'server_id' => '1', 'parent_domain_id' => '12', 'username' => 'shopdeploy', 'dir' => '/var/www/clients/client3/web12', 'quota_size' => '-1', 'active' => 'y']),
            TestCase::ok([['ftp_user_id' => '14'], ['ftp_user_id' => '15']]),
        ],
        'sites_ftp_user_update' => TestCase::ok(1),
        'sites_ftp_user_delete' => TestCase::ok(1),
        'sites_ftp_user_server_get' => [TestCase::ok(['hostname' => 'web1.example.com', 'ip_address' => '192.0.2.5']), TestCase::ok(false)],
    ]);

    $ftp = ISPConfig::ftp();
    $user = $ftp->get(14);

    expect($user?->directory)->toBe('/var/www/clients/client3/web12')
        ->and($user?->quotaMb)->toBe(-1)
        ->and($ftp->all())->toHaveCount(2)
        ->and($ftp->changePassword(3, 14, 'Ftp-Secret-2'))->toBe(1)
        ->and($ftp->delete(14))->toBe(1)
        ->and($ftp->server('shopdeploy'))->toBe(['hostname' => 'web1.example.com', 'ip_address' => '192.0.2.5'])
        ->and($ftp->server('unknown'))->toBeNull();

    assertRemoteCall('sites_ftp_user_update', ['client_id' => 3, 'primary_id' => 14, 'params' => ['password' => 'Ftp-Secret-2']]);
    assertRemoteCall('sites_ftp_user_delete', ['primary_id' => 14]);
    assertRemoteCall('sites_ftp_user_server_get', ['ftp_user' => 'shopdeploy']);
});

it('queues backup downloads and deletions', function (): void {
    TestCase::api([
        'mail_user_backup_list' => TestCase::ok([['backup_id' => '60', 'server_id' => '1', 'mailuser_id' => '17', 'tstamp' => '0', 'filename' => 'mail17.tar.gz']]),
        'sites_web_domain_backup' => TestCase::ok('1'),
        'mail_user_backup' => TestCase::ok(false),
    ]);

    $backups = ISPConfig::backups();
    $mail = $backups->ofMailbox();

    expect($mail[0]->type)->toBe('mail')
        ->and($mail[0]->parentId)->toBe(17)
        ->and($mail[0]->createdAt)->toBeNull()
        ->and($mail[0]->sizeBytes)->toBeNull()
        ->and($backups->download(55))->toBeTrue()
        ->and($backups->delete(56))->toBeTrue()
        ->and($backups->deleteMailbox(60))->toBeFalse();

    assertRemoteCall('mail_user_backup_list', ['primary_id' => null]);
    assertRemoteCall('sites_web_domain_backup', ['primary_id' => 55, 'action_type' => 'backup_download']);
    assertRemoteCall('sites_web_domain_backup', ['primary_id' => 56, 'action_type' => 'backup_delete']);
    assertRemoteCall('mail_user_backup', ['primary_id' => 60, 'action_type' => 'backup_delete_mail']);
});

it('reads server configuration, ids and php versions', function (): void {
    TestCase::api([
        'server_get' => [TestCase::ok(['hostname' => 'web1.example.com']), TestCase::ok(false)],
        'server_get_serverid_by_ip' => [TestCase::ok([['server_id' => '3']]), TestCase::ok('4'), TestCase::ok([])],
        'server_get_php_versions' => TestCase::ok(['PHP 8.3', 'PHP 8.4']),
        'server_get_functions' => TestCase::ok(['web_server' => '1']),
        'monitor_jobqueue_count' => TestCase::ok(null),
        'server_get_app_version' => TestCase::ok([]),
    ]);

    $server = ISPConfig::server();

    expect($server->config(1, 'server'))->toBe(['hostname' => 'web1.example.com'])
        ->and($server->config(1))->toBe([])
        ->and($server->idByIp('192.0.2.5'))->toBe(3)
        ->and($server->idByIp('192.0.2.6'))->toBe(4)
        ->and($server->idByIp('192.0.2.7'))->toBeNull()
        ->and($server->phpVersions(1, 'fast-cgi', true))->toBe(['PHP 8.3', 'PHP 8.4'])
        ->and($server->functions(1))->toBe(['web_server' => '1'])
        ->and($server->pendingJobs(2))->toBe(0)
        ->and($server->version(1))->toBeNull();

    assertRemoteCall('server_get', ['server_id' => 1, 'section' => 'server']);
    assertRemoteCall('server_get', ['server_id' => 1, 'section' => '']);
    assertRemoteCall('server_get_serverid_by_ip', ['ipaddress' => '192.0.2.5']);
    assertRemoteCall('server_get_php_versions', ['server_id' => 1, 'php' => 'fast-cgi', 'get_full_data' => true]);
    assertRemoteCall('monitor_jobqueue_count', ['server_id' => 2]);
});

it('rejects add answers without a positive id', function (mixed $answer): void {
    TestCase::api(['client_add' => TestCase::ok($answer)]);

    expect(fn() => ISPConfig::clients()->create(['username' => 'shop']))
        ->toThrow(ApiException::class, 'ISPConfig client_add returned an unexpected response (HTTP 200).');
})->with([
    'zero' => [0],
    'false' => [false],
    'text' => ['error'],
]);

it('counts affected records from loosely typed answers', function (): void {
    TestCase::api(['sites_web_domain_update' => [TestCase::ok(true), TestCase::ok('2'), TestCase::ok(false)]]);

    $sites = ISPConfig::sites();

    expect($sites->update(3, 12, ['php' => 'php-fpm']))->toBe(1)
        ->and($sites->update(3, 12, ['php' => 'php-fpm']))->toBe(2)
        ->and($sites->update(3, 12, ['php' => 'php-fpm']))->toBe(0);
});

it('returns empty lists for answers that are not arrays', function (): void {
    TestCase::api([
        'sites_web_domain_get' => TestCase::ok(false),
        'quota_get_by_user' => TestCase::ok('none'),
        'trafficquota_get_by_user' => TestCase::ok(null),
        'client_get_all' => TestCase::ok(['1', 'x', 2]),
    ]);

    expect(ISPConfig::sites()->all())->toBe([])
        ->and(ISPConfig::sites()->quota(3))->toBe([])
        ->and(ISPConfig::sites()->traffic(3))->toBe([])
        ->and(ISPConfig::clients()->ids())->toBe([1, 2]);
});
