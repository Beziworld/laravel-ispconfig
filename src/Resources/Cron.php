<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\CronJob;
use InvalidArgumentException;

/**
 * Cron jobs of websites.
 */
final class Cron extends Resource
{
    public function get(int $cronId): ?CronJob
    {
        return $this->find('sites_cron_get', 'cron_id', $cronId, CronJob::fromArray(...));
    }

    /**
     * All cron jobs, or the jobs matching a filter such as ['parent_domain_id' => 5].
     *
     * @param array<string, scalar> $filter
     * @return list<CronJob>
     */
    public function all(array $filter = []): array
    {
        return $this->search('sites_cron_get', 'cron_id', $filter, CronJob::fromArray(...));
    }

    /**
     * Creates a cron job of a website.
     *
     * @param string $schedule Five crontab fields, e.g. "* * * * *" or "30 3 * * 1-5", or "@reboot".
     * @param string $type "url" (the server calls the URL), "chrooted" (command in the jail of the website) or "full".
     * @param string $command URL or shell command; {DOMAIN} is replaced with the domain of the website.
     */
    public function create(
        int $clientId,
        int $serverId,
        int $websiteId,
        string $schedule,
        string $command,
        string $type = 'url',
        bool $log = false,
    ): int {
        return $this->insert('sites_cron_add', [
            'client_id' => $clientId,
            'params' => [
                'server_id' => $serverId,
                'parent_domain_id' => $websiteId,
                ...self::schedule($schedule),
                'type' => $type,
                'command' => $command,
                'log' => $log ? 'y' : 'n',
                'active' => 'y',
            ],
        ]);
    }

    /**
     * Changes the given fields; "schedule" is accepted as a shortcut for the five run_* fields.
     *
     * @param array<string, mixed> $params
     */
    public function update(int $clientId, int $cronId, array $params): int
    {
        if (isset($params['schedule']) && is_string($params['schedule'])) {
            $params = self::schedule($params['schedule']) + $params;
            unset($params['schedule']);
        }

        return $this->affected('sites_cron_update', ['client_id' => $clientId, 'cron_id' => $cronId, 'params' => $params]);
    }

    public function setActive(int $clientId, int $cronId, bool $active): int
    {
        return $this->update($clientId, $cronId, ['active' => $active ? 'y' : 'n']);
    }

    public function delete(int $cronId): int
    {
        return $this->affected('sites_cron_delete', ['cron_id' => $cronId]);
    }

    /**
     * Splits a crontab expression into the run_* fields of ISPConfig.
     *
     * @return array{run_min: string, run_hour: string, run_mday: string, run_month: string, run_wday: string}
     */
    public static function schedule(string $expression): array
    {
        $expression = trim($expression);

        if ($expression === '@reboot') {
            return ['run_min' => '*', 'run_hour' => '*', 'run_mday' => '*', 'run_month' => '@reboot', 'run_wday' => '*'];
        }

        $fields = preg_split('/\s+/', $expression);

        if ($fields === false || count($fields) !== 5) {
            throw new InvalidArgumentException(sprintf('The cron schedule "%s" must have five fields.', $expression));
        }

        return [
            'run_min' => $fields[0],
            'run_hour' => $fields[1],
            'run_mday' => $fields[2],
            'run_month' => $fields[3],
            'run_wday' => $fields[4],
        ];
    }
}
