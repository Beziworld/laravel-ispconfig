<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Resource record of a primary DNS zone (table "dns_rr").
 */
final readonly class DnsRecord
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $zoneId,
        public string $name,
        public string $type,
        public string $data,
        public int $priority,
        public ?int $ttl,
        public bool $active,
        public ?CarbonImmutable $changedAt,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $timezone = 'UTC'): self
    {
        return new self(
            id: Values::int($data, 'id'),
            zoneId: Values::int($data, 'zone'),
            name: Values::string($data, 'name'),
            type: strtoupper(Values::string($data, 'type')),
            data: Values::string($data, 'data'),
            priority: Values::int($data, 'aux'),
            ttl: Values::nullableInt($data, 'ttl'),
            active: Values::bool($data, 'active'),
            changedAt: Values::date($data, 'stamp', $timezone),
            raw: $data,
        );
    }
}
