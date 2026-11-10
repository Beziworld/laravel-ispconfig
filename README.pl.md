# Laravel ISPConfig

[![tests](https://github.com/beziworld/laravel-ispconfig/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-ispconfig/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-ispconfig.svg)](https://packagist.org/packages/beziworld/laravel-ispconfig)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-ispconfig.svg)](LICENSE.md)

Klient Laravel dla zdalnego API panelu [ISPConfig 3](https://www.ispconfig.org): klienci i resellerzy, strony WWW,
subdomeny i domeny aliasowe, moduł domen, strefy i rekordy DNS, domeny pocztowe, skrzynki, aliasy i
przekierowania, bazy danych, zadania cron, konta FTP, kopie zapasowe oraz informacje o serwerach, dla jednego
lub wielu paneli.

[English documentation](README.md)

## Funkcje

- Endpoint JSON (`remote/json.php`) zdalnego API: bez `ext-soap` i bez kopert SOAP
- Obsługa sesji po stronie pakietu: leniwy `login`, identyfikator sesji w cache Laravela poniżej 30-minutowego
  limitu ważności, jedno przezroczyste ponowne logowanie, gdy ISPConfig zgłosi wygaśnięcie sesji, `logout()` na
  koniec pracy
- Nieudane logowania nigdy nie są powtarzane, więc limit 10 prób na 5 minut nie zablokuje Twojego serwera
- Typowane wyniki (`Client`, `Website`, `DnsZone`, `DnsRecord`, `MailDomain`, `Mailbox`, `Database`, `CronJob`,
  `FtpUser`, `Backup`, ...) z pełnym rekordem w `raw`
- Kody błędów odtwarzane z odpowiedzi JSON (`permission_denied`, `session_does_not_exist`,
  `data_processing_error`, `duplicate_action`, ...) i typowane wyjątki
- Funkcje odczytu są powtarzane raz po błędzie połączenia lub HTTP 5xx; funkcje zmieniające dane są wysyłane
  dokładnie raz
- Wiele nazwanych połączeń oraz połączenia w locie przez `ISPConfig::using([...])`
- `call()` dla każdej z ponad 300 funkcji zdalnych, `php artisan ispconfig:check` do sprawdzenia połączenia
- PHP 8.3+, Laravel 12 i 13, PHPStan na poziomie 8

## Dlaczego ten pakiet

Istniejące klienty PHP dla ISPConfig (`gdm/ispconfig-soap-client`, `pemedina/ispconfig-wrapper`,
`quattrozeta/ispconfig-soap-client` i inne) opakowują endpoint SOAP, wymagają `ext-soap` i zostawiają Tobie
obsługę sesji, jej wygasanie po 30 minutach oraz limit nieudanych logowań. Jedyny pakiet Laravel znaleziony na
Packagist (`smart-dato/isp-config`, 7 pobrań w listopadzie 2026) to cienkie SDK. Ten pakiet dodaje konfigurację
Laravela z wieloma panelami, sesje w cache, typowane obiekty danych, odtworzone kody błędów, wsparcie dla
`Http::fake()` w Twoich testach i komendę Artisan, zgodnie z dokumentacją dołączaną do ISPConfig 3.3.3p2
(`remoting_client/API-docs`).

## Instalacja

```bash
composer require beziworld/laravel-ispconfig
php artisan vendor:publish --tag=ispconfig-config   # opcjonalnie
```

```dotenv
ISPCONFIG_URL=https://panel.example.com:8080   # /remote/json.php zostanie dopisane
ISPCONFIG_USERNAME=laravel                     # użytkownik zdalny (System > Remote Users)
ISPCONFIG_PASSWORD=secret
ISPCONFIG_VERIFY_SSL=true                      # false lub ścieżka do pakietu CA dla certyfikatów self-signed
ISPCONFIG_SESSION_TTL=1500                     # sekundy, poniżej 1800 s ważności sesji
ISPCONFIG_CACHE_STORE=                         # magazyn cache dla identyfikatora sesji (pusty: domyślny)
```

```bash
php artisan ispconfig:check
```

Utwórz użytkownika zdalnego w *System > Remote Users*, włącz tylko te grupy funkcji, których potrzebuje
aplikacja, zaznacz *Remote Access* i wpisz adres serwera aplikacji. Sesja jest powiązana z adresem klienta,
dlatego wszystkie żądania jednego połączenia muszą wychodzić z tego samego IP.

## Przykłady użycia

### Klient, strona WWW i skrzynka po opłaceniu zamówienia

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
                'language' => 'pl',
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

            ISPConfig::email()->createDomain($clientId, 1, $this->order->domain);
            ISPConfig::email()->createMailbox($clientId, 1, 'biuro@' . $this->order->domain, Str::password(16), quotaMb: 2048);
        } catch (ApiException $e) {
            // $e->faultCode(): "data_processing_error" dla odrzuconych pól, "permission_denied", ...
            $this->fail($e);

            return;
        }

        $this->order->update(['ispconfig_client_id' => $clientId, 'ispconfig_website_id' => $websiteId]);
    }
}
```

### Klienci

```php
$client = ISPConfig::clients()->get(3);                     // ?Client
$client = ISPConfig::clients()->findByUsername('sklep');
$clients = ISPConfig::clients()->all(['company_name' => 'Sklep%']);  // % porównuje przez LIKE

ISPConfig::clients()->update(3, ['telephone' => '+48 22 000 00 00']); // zmieniają się tylko wysłane pola
ISPConfig::clients()->lock(3);                                // blokuje strony, pocztę, ... klienta
ISPConfig::clients()->lock(3, false);
ISPConfig::clients()->changePassword(3, $noweHaslo);
ISPConfig::clients()->deleteEverything(3);                    // klient ze wszystkimi rekordami
```

### Strony WWW, subdomeny, domeny aliasowe i Let's Encrypt

```php
$site = ISPConfig::sites()->findByDomain('sklep.example.com');  // ?Website

ISPConfig::sites()->update($clientId, $site->id, ['hd_quota' => 4096, 'php' => 'php-fpm', 'server_php_id' => 2]);
ISPConfig::sites()->enableLetsEncrypt($clientId, $site->id);    // ssl=y, ssl_letsencrypt=y
ISPConfig::sites()->setActive($site->id, false);                // zawieszenie strony
ISPConfig::sites()->createAliasDomain($clientId, $site->id, 'sklep.example.net');
ISPConfig::sites()->createSubdomain($clientId, $site->id, 'blog.sklep.example.com');

ISPConfig::sites()->quota($clientId);    // zajętość dysku per strona
ISPConfig::sites()->traffic($clientId);  // transfer per domena w bajtach
```

### Strefy i rekordy DNS

```php
$dns = ISPConfig::dns();

$zoneId = $dns->createZone($clientId, [
    'server_id' => 1,
    'origin' => 'sklep.example.com',             // kropka na końcu zostanie dodana
    'ns' => 'ns1.example.com.',
    'mbox' => 'hostmaster.sklep.example.com.',
    'serial' => (int) now()->format('Ymd01'),
]);
// albo z szablonu DNS, jak kreator w panelu:
$dns->createZoneFromTemplate($clientId, 'sklep.example.com', '192.0.2.10', 'ns1.example.com', 'ns2.example.com', 'hostmaster@sklep.example.com');

$dns->createRecord($clientId, $zoneId, 'A', 'www', '192.0.2.10', ttl: 300);
$dns->createRecord($clientId, $zoneId, 'MX', '@', 'mail.example.com.', params: ['aux' => 10]);
$dns->createRecord($clientId, $zoneId, 'TXT', '_dmarc', 'v=DMARC1; p=quarantine');

foreach ($dns->records($zoneId) as $record) {
    // $record->type, $record->name, $record->data, $record->priority, $record->ttl
}

$dns->updateRecord($clientId, 'A', $recordId, ['data' => '192.0.2.20']);
$dns->deleteRecord('TXT', $recordId);
$dns->zoneId('sklep.example.com');           // ?int
```

Każda zmiana podnosi numer seryjny strefy; przekaż `updateSerial: false`, aby go zachować.

### Poczta

```php
$mail = ISPConfig::email();

$mail->createDomain($clientId, serverId: 1, domain: 'sklep.example.com', params: ['dkim' => 'n']);
$mailboxId = $mail->createMailbox($clientId, 1, 'biuro@sklep.example.com', $haslo, quotaMb: 2048, params: ['name' => 'Biuro']);
$mail->changeMailboxQuota($clientId, $mailboxId, 4096);
$mail->changeMailboxPassword($clientId, $mailboxId, $noweHaslo);

$mail->createAlias($clientId, 1, 'sprzedaz@sklep.example.com', 'biuro@sklep.example.com');
$mail->createForward($clientId, 1, 'prezes@sklep.example.com', ['ceo@example.net', 'asystent@example.net']);
$mail->createCatchall($clientId, 1, 'sklep.example.com', 'biuro@sklep.example.com');

$mail->mailboxesOfClient($clientId);   // list<Mailbox>
$mail->quota($clientId);               // used_raw, quota_raw, used_percentage per skrzynka
$mail->setDomainActive($domainId, false);
```

### Bazy danych

```php
$userId = ISPConfig::databases()->createUser($clientId, 'c3sklep', $haslo);   // API nie dodaje prefiksu do nazw
$databaseId = ISPConfig::databases()->create($clientId, serverId: 1, websiteId: $websiteId, name: 'c3sklep', userId: $userId, params: [
    'remote_access' => 'y',
    'remote_ips' => '10.0.0.8',
]);

ISPConfig::databases()->changeUserPassword($clientId, $userId, $noweHaslo);
ISPConfig::databases()->ofClient($clientId);
```

### Cron, FTP i kopie zapasowe

```php
ISPConfig::cron()->create($clientId, 1, $websiteId, '* * * * *', 'https://sklep.example.com/cron', type: 'url');
ISPConfig::cron()->update($clientId, $cronId, ['schedule' => '*/5 * * * *']);

$site = ISPConfig::sites()->get($websiteId);
ISPConfig::ftp()->create($clientId, $site, 'sklepdeploy', $haslo, quotaMb: 1024);   // uid, gid i katalog ze strony

foreach (ISPConfig::backups()->ofWebsite($websiteId) as $backup) {
    // $backup->type ('web', 'mysql'), $backup->filename, $backup->sizeBytes, $backup->createdAt
}
ISPConfig::backups()->restore($backupId);      // w kolejce, wykona się przy następnym uruchomieniu crona serwera
ISPConfig::backups()->download($backupId);     // kopiuje kopię do katalogu "backup" strony
```

### Wiele paneli

```php
// config/ispconfig.php
'connections' => [
    'default' => [...],
    'panel2' => ['url' => env('ISPC2_URL'), 'username' => env('ISPC2_USER'), 'password' => env('ISPC2_PASSWORD')],
],

ISPConfig::connection('panel2')->sites()->all();

// Panele zapisane w bazie danych
ISPConfig::using([
    'url' => $panel->url,
    'username' => $panel->username,
    'password' => decrypt($panel->password),
])->server()->version();
```

### Wszystko inne

```php
ISPConfig::call('sites_shell_user_add', ['client_id' => 3, 'params' => [...]]);   // session_id jest dodawany
ISPConfig::server()->functionList();   // nazwy wszystkich funkcji zdalnych
ISPConfig::server()->pendingJobs();    // zmiany jeszcze nieprzetworzone przez serwery
ISPConfig::logout();
```

## Wyjątki

Wszystkie wyjątki dziedziczą po `ISPConfigException`:

| Wyjątek | Kiedy |
|---|---|
| `AuthenticationException` | Odrzucone logowanie (`login_failed`, `login_failure_limit`, `session_failure_limit`, `session_ip_mismatch`). Nigdy nie jest ponawiane. Dziedziczy po `ApiException`. |
| `ApiException` | API zgłosiło błąd lub jest nieosiągalne: `function()`, `errorCode()` (`remote_fault`, `invalid_method`, ...), `faultCode()` (`permission_denied`, `data_processing_error`, ...), `httpStatus()`, `payload()`, `isPermissionDenied()`, `isValidationError()` |
| `RateLimitException` | HTTP 429 z proxy przed panelem, `retryAfter()` w sekundach |
| `InvalidConfiguration` | Nieznane połączenie lub brak adresu / danych logowania |

## Uwagi

- Zmiany trafiają od razu do bazy panelu, a serwery wprowadzają je przy następnym uruchomieniu crona (zwykle w
  ciągu minuty); `server()->pendingJobs()` pokazuje kolejkę.
- Zdalne API działa z uprawnieniami administratora dla włączonych funkcji. `client_id` w funkcjach dodających
  ustawia właściciela nowego rekordu (0: administrator).
- ISPConfig zapisuje limity skrzynek w bajtach; `createMailbox()` i `changeMailboxQuota()` przyjmują MB.
- ISPConfig 3.3.3 i nowsze usuwają pola haseł z każdego wyniku.

## Rozwój

```bash
composer install
composer ci   # pint --test, phpstan (poziom 8), pest
```

## Autor

Pakiet tworzy i utrzymuje **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Zgłoszenia i propozycje: [GitHub issues](https://github.com/beziworld/laravel-ispconfig/issues)
- Podatności bezpieczeństwa: zobacz [SECURITY.md](SECURITY.md)

Potrzebujesz integracji? Skontaktuj się przez [beziworld.eu](https://beziworld.eu).

### Inne pakiety autora

- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): panel DirectAdmin
- [beziworld/laravel-cpanel](https://github.com/beziworld/laravel-cpanel): cPanel i WHM
- [beziworld/laravel-plesk](https://github.com/beziworld/laravel-plesk): Plesk

## Licencja

MIT. Zobacz [LICENSE.md](LICENSE.md). Pakiet nie jest powiązany z projektem ISPConfig ani jego twórcami.
