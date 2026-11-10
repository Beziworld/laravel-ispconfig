<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Mail domain (table "mail_domain").
 */
final readonly class MailDomain
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public string $domain,
        public bool $dkim,
        public ?string $dkimSelector,
        public ?string $dkimPublicKey,
        public bool $active,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'domain_id'),
            serverId: Values::int($data, 'server_id'),
            domain: Values::string($data, 'domain'),
            dkim: Values::bool($data, 'dkim'),
            dkimSelector: Values::nullableString($data, 'dkim_selector'),
            dkimPublicKey: Values::nullableString($data, 'dkim_public'),
            active: Values::bool($data, 'active'),
            raw: $data,
        );
    }
}
