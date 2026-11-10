<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Console;

use Beziworld\ISPConfig\Exceptions\ISPConfigException;
use Beziworld\ISPConfig\ISPConfigManager;
use Illuminate\Console\Command;

/**
 * Verifies that a configured ISPConfig connection can log in and lists its servers.
 */
final class CheckConnectionCommand extends Command
{
    /** @var string */
    protected $signature = 'ispconfig:check {connection? : Connection name from config/ispconfig.php}';

    /** @var string */
    protected $description = 'Check the remote user login of an ISPConfig connection';

    public function handle(ISPConfigManager $manager): int
    {
        $argument = $this->input->getArgument('connection');
        $name = is_string($argument) && $argument !== '' ? $argument : $manager->defaultConnection();

        try {
            $client = $manager->connection($name);
            $client->connection()->forgetSession();
            $client->connection()->login();
            $version = $client->server()->version();
        } catch (ISPConfigException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $config = $client->connection()->config();

        $this->components->info(sprintf('Logged in to %s as %s.', $config->url, $config->username));
        $this->components->twoColumnDetail('ISPConfig version', $version ?? 'n/a');

        try {
            foreach ($client->server()->all() as $server) {
                $this->components->twoColumnDetail('Server ' . $server->id, $server->name);
            }
        } catch (ISPConfigException $exception) {
            $this->components->warn('Servers are not listed: ' . $exception->getMessage());
        }

        return self::SUCCESS;
    }
}
