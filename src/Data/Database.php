<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Database of a website (table "web_database").
 */
final readonly class Database
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public int $websiteId,
        public string $name,
        public string $type,
        public int $userId,
        public int $readOnlyUserId,
        public ?string $userName,
        public ?int $quotaMb,
        public bool $remoteAccess,
        public bool $active,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'database_id'),
            serverId: Values::int($data, 'server_id'),
            websiteId: Values::int($data, 'parent_domain_id'),
            name: Values::string($data, 'database_name'),
            type: Values::string($data, 'type', 'mysql'),
            userId: Values::int($data, 'database_user_id'),
            readOnlyUserId: Values::int($data, 'database_ro_user_id'),
            userName: Values::nullableString($data, 'database_user'),
            quotaMb: Values::nullableInt($data, 'database_quota'),
            remoteAccess: Values::bool($data, 'remote_access'),
            active: Values::bool($data, 'active'),
            raw: $data,
        );
    }
}
