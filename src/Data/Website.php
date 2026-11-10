<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Website, subdomain or alias domain record (table "web_domain").
 */
final readonly class Website
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public string $domain,
        public string $type,
        public int $parentDomainId,
        public ?string $documentRoot,
        public ?string $systemUser,
        public ?string $systemGroup,
        public ?string $php,
        public ?int $diskQuotaMb,
        public ?int $trafficQuotaMb,
        public bool $ssl,
        public bool $letsEncrypt,
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
            type: Values::string($data, 'type', 'vhost'),
            parentDomainId: Values::int($data, 'parent_domain_id'),
            documentRoot: Values::nullableString($data, 'document_root'),
            systemUser: Values::nullableString($data, 'system_user'),
            systemGroup: Values::nullableString($data, 'system_group'),
            php: Values::nullableString($data, 'php'),
            diskQuotaMb: Values::nullableInt($data, 'hd_quota'),
            trafficQuotaMb: Values::nullableInt($data, 'traffic_quota'),
            ssl: Values::bool($data, 'ssl'),
            letsEncrypt: Values::bool($data, 'ssl_letsencrypt'),
            active: Values::bool($data, 'active'),
            raw: $data,
        );
    }
}
