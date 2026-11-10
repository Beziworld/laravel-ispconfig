<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig;

use Beziworld\ISPConfig\Console\CheckConnectionCommand;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the ISPConfig connection manager and the console command.
 */
final class ISPConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ispconfig.php', 'ispconfig');

        $this->app->singleton(ISPConfigManager::class, static fn(Application $app): ISPConfigManager => new ISPConfigManager(
            $app->make(Config::class),
            $app->make(HttpFactory::class),
            $app->make(CacheFactory::class),
        ));

        $this->app->bind(ISPConfigClient::class, static fn(Application $app): ISPConfigClient => $app->make(ISPConfigManager::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ispconfig.php' => $this->app->configPath('ispconfig.php'),
            ], 'ispconfig-config');

            $this->commands([CheckConnectionCommand::class]);
        }
    }
}
