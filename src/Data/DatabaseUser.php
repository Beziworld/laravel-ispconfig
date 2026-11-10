<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Database user (table "web_database_user"). Passwords are never part of API results.
 */
final readonly class DatabaseUser
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public string $username,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'database_user_id'),
            serverId: Values::int($data, 'server_id'),
            username: Values::string($data, 'database_user'),
            raw: $data,
        );
    }
}
