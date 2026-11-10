# Laravel ISPConfig

[![tests](https://github.com/beziworld/laravel-ispconfig/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-ispconfig/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-ispconfig.svg)](https://packagist.org/packages/beziworld/laravel-ispconfig)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-ispconfig.svg)](LICENSE.md)

Laravel client for the [ISPConfig 3](https://www.ispconfig.org) remote API: clients and resellers, websites,
subdomains and alias domains, the domain module, DNS zones and records, mail domains, mailboxes, aliases and
forwards, databases, cron jobs, FTP users, backups and server information, for one or many panels.

[Polska wersja dokumentacji](README.pl.md)

## Features

- JSON endpoint (`remote/json.php`) of the remote API: no `ext-soap`, no SOAP envelopes
- Session handling done for you: lazy `login`, session ID cached in the Laravel cache below the 30 minute lifetime,
  one transparent re-login when ISPConfig reports an expired session, `logout()` when you are done
- Failed logins are never repeated, so the 10 attempts per 5 minutes limit of ISPConfig does not lock your
  server out
- Typed results (`Client`, `Website`, `DnsZone`, `DnsRecord`, `MailDomain`, `Mailbox`, `Database`, `CronJob`,
  `FtpUser`, `Backup`, ...) with the full record in `raw`
- Fault codes restored from the JSON answer (`permission_denied`, `session_does_not_exist`,
  `data_processing_error`, `duplicate_action`, ...) and typed exceptions
- Read functions are repeated once after a connection error or HTTP 5xx; functions that change data are sent
  exactly once
- Multiple named connections plus runtime connections with `ISPConfig::using([...])`
- `call()` for any of the 300+ remote functions, `php artisan ispconfig:check` to test a connection
- PHP 8.3+, Laravel 12 and 13, PHPStan level 8

## Why this package

Existing PHP clients for ISPConfig (`gdm/ispconfig-soap-client`, `pemedina/ispconfig-wrapper`,
`quattrozeta/ispconfig-soap-client` and others) wrap the SOAP endpoint, require `ext-soap` and leave the session
handling, the expiry after 30 minutes and the login failure limit to you. The only Laravel package found on
Packagist (`smart-dato/isp-config`, 7 downloads in November 2026) is a thin SDK. This package adds Laravel
configuration with several panels, cached sessions, typed data objects, restored fault codes, `Http::fake()`
support for your own tests and an Artisan check, following the documentation shipped with ISPConfig 3.3.3p2
(`remoting_client/API-docs`).

## Installation

```bash
composer require beziworld/laravel-ispconfig
php artisan vendor:publish --tag=ispconfig-config   # optional
```

```dotenv
ISPCONFIG_URL=https://panel.example.com:8080   # /remote/json.php is appended
ISPCONFIG_USERNAME=laravel                     # a remote user (System > Remote Users)
ISPCONFIG_PASSWORD=secret
ISPCONFIG_VERIFY_SSL=true                      # false or a CA bundle path for self-signed panels
ISPCONFIG_SESSION_TTL=1500                     # seconds, below the 1800 s session lifetime
ISPCONFIG_CACHE_STORE=                         # cache store for the session ID (default store when empty)
```

```bash
php artisan ispconfig:check
```

Create the remote user under *System > Remote Users*, enable only the function groups your application needs,
switch *Remote Access* on and enter the address of your application server. A session is bound to the client
address, so all requests of one connection must leave from the same IP.

## Usage examples

### Provision a client, a website and a mailbox after an order is paid

```php
use Beziworld\ISPConfig\Exceptions\ApiException;
use Beziworld\ISPConfig\Facades\ISPConfig;

class ProvisionHosting implements ShouldQueue
{
    public function handle(): void
    {
        try {
            $clientId = ISPConfig::clients()->create([
                'company_name' => $this->order->company,
                'contact_name' => $this->order->last_name,
                'email' => $this->order->email,
                'username' => $this->order->username,
                'password' => $password = Str::password(16),
                'template_master' => $this->order->plan->ispconfig_template_id,
                'country' => 'PL',
                'language' => 'en',
            ]);

            $websiteId = ISPConfig::sites()->create($clientId, [
                'server_id' => 1,
                'ip_address' => '*',
                'domain' => $this->order->domain,
                'hd_quota' => $this->order->plan->disk_mb,
                'traffic_quota' => -1,
                'php' => 'php-fpm',
                'subdomain' => 'www',
                'active' => 'y',
            ]);

            $mailDomainId = ISPConfig::email()->createDomain($clientId, 1, $this->order->domain);
            ISPConfig::email()->createMailbox($clientId, 1, 'office@' . $this->order->domain, Str::password(16), quotaMb: 2048);
        } catch (ApiException $e) {
            // $e->faultCode(): "data_processing_error" for refused fields, "permission_denied", ...
            $this->fail($e);

            return;
        }

        $this->order->update(['ispconfig_client_id' => $clientId, 'ispconfig_website_id' => $websiteId]);
    }
}
```

### Clients

```php
$client = ISPConfig::clients()->get(3);                     // ?Client
$client = ISPConfig::clients()->findByUsername('shop');
$clients = ISPConfig::clients()->all(['company_name' => 'Shop%']);   // % compares with LIKE

ISPConfig::clients()->update(3, ['telephone' => '+48 22 000 00 00']); // only sent fields change
ISPConfig::clients()->lock(3);                                // locks websites, mail, ... of the client
ISPConfig::clients()->lock(3, false);
ISPConfig::clients()->changePassword(3, $newPassword);
ISPConfig::clients()->deleteEverything(3);                    // client with all its records
```

### Websites, subdomains, alias domains and Let's Encrypt

```php
$site = ISPConfig::sites()->findByDomain('shop.example.com');  // ?Website

ISPConfig::sites()->update($clientId, $site->id, ['hd_quota' => 4096, 'php' => 'php-fpm', 'server_php_id' => 2]);
ISPConfig::sites()->enableLetsEncrypt($clientId, $site->id);   // ssl=y, ssl_letsencrypt=y
ISPConfig::sites()->setActive($site->id, false);               // suspend the website
ISPConfig::sites()->createAliasDomain($clientId, $site->id, 'shop.example.net');
ISPConfig::sites()->createSubdomain($clientId, $site->id, 'blog.shop.example.com');

ISPConfig::sites()->quota($clientId);    // disk usage per website
ISPConfig::sites()->traffic($clientId);  // traffic per domain in bytes
```

### DNS zones and records

```php
$dns = ISPConfig::dns();

$zoneId = $dns->createZone($clientId, [
    'server_id' => 1,
    'origin' => 'shop.example.com',              // the trailing dot is added
    'ns' => 'ns1.example.com.',
    'mbox' => 'hostmaster.shop.example.com.',
    'serial' => (int) now()->format('Ymd01'),
]);
// or from a DNS template, like the wizard of the panel:
$dns->createZoneFromTemplate($clientId, 'shop.example.com', '192.0.2.10', 'ns1.example.com', 'ns2.example.com', 'hostmaster@shop.example.com');

$dns->createRecord($clientId, $zoneId, 'A', 'www', '192.0.2.10', ttl: 300);
$dns->createRecord($clientId, $zoneId, 'MX', '@', 'mail.example.com.', params: ['aux' => 10]);
$dns->createRecord($clientId, $zoneId, 'TXT', '_dmarc', 'v=DMARC1; p=quarantine');

foreach ($dns->records($zoneId) as $record) {
    // $record->type, $record->name, $record->data, $record->priority, $record->ttl
}

$dns->updateRecord($clientId, 'A', $recordId, ['data' => '192.0.2.20']);
$dns->deleteRecord('TXT', $recordId);
$dns->zoneId('shop.example.com');            // ?int
```

Every change increases the serial of the zone; pass `updateSerial: false` to keep it.

### Mail

```php
$mail = ISPConfig::email();

$mail->createDomain($clientId, serverId: 1, domain: 'shop.example.com', params: ['dkim' => 'n']);
$mailboxId = $mail->createMailbox($clientId, 1, 'office@shop.example.com', $password, quotaMb: 2048, params: ['name' => 'Office']);
$mail->changeMailboxQuota($clientId, $mailboxId, 4096);
$mail->changeMailboxPassword($clientId, $mailboxId, $newPassword);

$mail->createAlias($clientId, 1, 'sales@shop.example.com', 'office@shop.example.com');
$mail->createForward($clientId, 1, 'boss@shop.example.com', ['ceo@example.net', 'assistant@example.net']);
$mail->createCatchall($clientId, 1, 'shop.example.com', 'office@shop.example.com');

$mail->mailboxesOfClient($clientId);   // list<Mailbox>
$mail->quota($clientId);               // used_raw, quota_raw, used_percentage per mailbox
$mail->setDomainActive($domainId, false);
```

### Databases

```php
$userId = ISPConfig::databases()->createUser($clientId, 'c3shop', $password);   // names are not prefixed by the API
$databaseId = ISPConfig::databases()->create($clientId, serverId: 1, websiteId: $websiteId, name: 'c3shop', userId: $userId, params: [
    'remote_access' => 'y',
    'remote_ips' => '10.0.0.8',
]);

ISPConfig::databases()->changeUserPassword($clientId, $userId, $rotated);
ISPConfig::databases()->ofClient($clientId);
```

### Cron, FTP and backups

```php
ISPConfig::cron()->create($clientId, 1, $websiteId, '* * * * *', 'https://shop.example.com/cron', type: 'url');
ISPConfig::cron()->update($clientId, $cronId, ['schedule' => '*/5 * * * *']);

$site = ISPConfig::sites()->get($websiteId);
ISPConfig::ftp()->create($clientId, $site, 'shopdeploy', $password, quotaMb: 1024);   // uid, gid and dir from the website

foreach (ISPConfig::backups()->ofWebsite($websiteId) as $backup) {
    // $backup->type ('web', 'mysql'), $backup->filename, $backup->sizeBytes, $backup->createdAt
}
ISPConfig::backups()->restore($backupId);      // queued, runs with the next cron run of the server
ISPConfig::backups()->download($backupId);     // copies the backup into the "backup" folder of the website
```

### Several panels

```php
// config/ispconfig.php
'connections' => [
    'default' => [...],
    'panel2' => ['url' => env('ISPC2_URL'), 'username' => env('ISPC2_USER'), 'password' => env('ISPC2_PASSWORD')],
],

ISPConfig::connection('panel2')->sites()->all();

// Panels stored in the database
ISPConfig::using([
    'url' => $panel->url,
    'username' => $panel->username,
    'password' => decrypt($panel->password),
])->server()->version();
```

### Anything else

```php
ISPConfig::call('sites_shell_user_add', ['client_id' => 3, 'params' => [...]]);   // session_id is added
ISPConfig::server()->functionList();   // names of all remote functions
ISPConfig::server()->pendingJobs();    // changes the servers have not processed yet
ISPConfig::logout();
```

## Exceptions

All exceptions extend `ISPConfigException`:

| Exception | When |
|---|---|
| `AuthenticationException` | Login refused (`login_failed`, `login_failure_limit`, `session_failure_limit`, `session_ip_mismatch`). Never retried. Extends `ApiException`. |
| `ApiException` | The API reported an error or could not be reached: `function()`, `errorCode()` (`remote_fault`, `invalid_method`, ...), `faultCode()` (`permission_denied`, `data_processing_error`, ...), `httpStatus()`, `payload()`, `isPermissionDenied()`, `isValidationError()` |
| `RateLimitException` | HTTP 429 from a proxy in front of the panel, `retryAfter()` in seconds |
| `InvalidConfiguration` | Unknown connection or missing URL / credentials |

## Notes

- Changes are written to the panel database at once and applied by the servers with their next cron run
  (usually within a minute); `server()->pendingJobs()` shows the queue.
- The remote API acts with administrator rights for the enabled functions. `client_id` of the add functions sets
  the owner of the new record (0: the administrator).
- Mailbox quotas are stored in bytes by ISPConfig; `createMailbox()` and `changeMailboxQuota()` take MB.
- Password fields are removed from every result by ISPConfig 3.3.3 and newer.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues and feature requests: [GitHub issues](https://github.com/beziworld/laravel-ispconfig/issues)
- Security vulnerabilities: see [SECURITY.md](SECURITY.md)

Need an integration? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): DirectAdmin hosting panel
- [beziworld/laravel-cpanel](https://github.com/beziworld/laravel-cpanel): cPanel and WHM
- [beziworld/laravel-plesk](https://github.com/beziworld/laravel-plesk): Plesk

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with the ISPConfig project or its developers.
