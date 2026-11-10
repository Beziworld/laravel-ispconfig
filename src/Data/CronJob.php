<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Cron job of a website (table "cron").
 */
final readonly class CronJob
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public int $websiteId,
        public string $type,
        public string $command,
        public string $schedule,
        public bool $log,
        public bool $active,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'id'),
            serverId: Values::int($data, 'server_id'),
            websiteId: Values::int($data, 'parent_domain_id'),
            type: Values::string($data, 'type', 'url'),
            command: Values::string($data, 'command'),
            schedule: implode(' ', array_map(
                static fn(string $field): string => Values::string($data, $field, '*'),
                ['run_min', 'run_hour', 'run_mday', 'run_month', 'run_wday'],
            )),
            log: Values::bool($data, 'log'),
            active: Values::bool($data, 'active'),
            raw: $data,
        );
    }
}
