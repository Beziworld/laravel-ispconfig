<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * FTP user of a website (table "ftp_user"). Passwords are never part of API results.
 */
final readonly class FtpUser
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public int $websiteId,
        public string $username,
        public ?string $directory,
        public ?int $quotaMb,
        public bool $active,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'ftp_user_id'),
            serverId: Values::int($data, 'server_id'),
            websiteId: Values::int($data, 'parent_domain_id'),
            username: Values::string($data, 'username'),
            directory: Values::nullableString($data, 'dir'),
            quotaMb: Values::nullableInt($data, 'quota_size'),
            active: Values::bool($data, 'active'),
            raw: $data,
        );
    }
}
