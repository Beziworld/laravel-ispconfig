<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Primary DNS zone (table "dns_soa").
 */
final readonly class DnsZone
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public string $origin,
        public ?string $nameServer,
        public ?string $mailbox,
        public ?int $serial,
        public ?int $ttl,
        public bool $active,
        public bool $dnssec,
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
            origin: Values::string($data, 'origin'),
            nameServer: Values::nullableString($data, 'ns'),
            mailbox: Values::nullableString($data, 'mbox'),
            serial: Values::nullableInt($data, 'serial'),
            ttl: Values::nullableInt($data, 'ttl'),
            active: Values::bool($data, 'active'),
            dnssec: Values::bool($data, 'dnssec_wanted'),
            raw: $data,
        );
    }

    /**
     * Zone name without the trailing dot.
     */
    public function domain(): string
    {
        return rtrim($this->origin, '.');
    }
}
