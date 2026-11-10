<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Backup made by the backup cron job of a server (table "web_backup" or "mail_backup").
 */
final readonly class Backup
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public int $parentId,
        public string $type,
        public ?string $mode,
        public string $filename,
        public ?int $sizeBytes,
        public ?CarbonImmutable $createdAt,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'backup_id'),
            serverId: Values::int($data, 'server_id'),
            parentId: Values::int($data, 'parent_domain_id', Values::int($data, 'mailuser_id')),
            type: Values::string($data, 'backup_type', 'mail'),
            mode: Values::nullableString($data, 'backup_mode'),
            filename: Values::string($data, 'filename'),
            sizeBytes: Values::nullableInt($data, 'filesize'),
            createdAt: Values::date($data, 'tstamp'),
            raw: $data,
        );
    }
}
