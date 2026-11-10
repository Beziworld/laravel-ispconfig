<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Alias, forward, catchall or alias domain (table "mail_forwarding").
 */
final readonly class MailForwarding
{
    /**
     * @param array<array-key, mixed> $raw
     * @param list<string> $destinations
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public string $source,
        public array $destinations,
        public string $type,
        public bool $allowSendAs,
        public bool $active,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $destinations = preg_split('/[\s,]+/', Values::string($data, 'destination'), -1, PREG_SPLIT_NO_EMPTY);

        return new self(
            id: Values::int($data, 'forwarding_id'),
            serverId: Values::int($data, 'server_id'),
            source: Values::string($data, 'source'),
            destinations: $destinations === false ? [] : $destinations,
            type: Values::string($data, 'type'),
            allowSendAs: Values::bool($data, 'allow_send_as'),
            active: Values::bool($data, 'active'),
            raw: $data,
        );
    }
}
