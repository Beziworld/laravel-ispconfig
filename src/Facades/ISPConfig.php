<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Facades;

use Beziworld\ISPConfig\ISPConfigManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\ISPConfig\ISPConfigClient connection(?string $name = null)
 * @method static \Beziworld\ISPConfig\ISPConfigClient using(array<string, mixed> $config, string $name = 'runtime')
 * @method static string defaultConnection()
 * @method static void purge(?string $name = null)
 * @method static \Beziworld\ISPConfig\Resources\Clients clients()
 * @method static \Beziworld\ISPConfig\Resources\Sites sites()
 * @method static \Beziworld\ISPConfig\Resources\Domains domains()
 * @method static \Beziworld\ISPConfig\Resources\Dns dns()
 * @method static \Beziworld\ISPConfig\Resources\Email email()
 * @method static \Beziworld\ISPConfig\Resources\Databases databases()
 * @method static \Beziworld\ISPConfig\Resources\Cron cron()
 * @method static \Beziworld\ISPConfig\Resources\Ftp ftp()
 * @method static \Beziworld\ISPConfig\Resources\Backups backups()
 * @method static \Beziworld\ISPConfig\Resources\Server server()
 * @method static mixed call(string $function, array<string, mixed> $parameters = [])
 * @method static bool logout()
 *
 * @see ISPConfigManager
 * @see \Beziworld\ISPConfig\ISPConfigClient
 */
final class ISPConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ISPConfigManager::class;
    }
}
