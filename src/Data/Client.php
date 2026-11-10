<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Client or reseller record (table "client").
 */
final readonly class Client
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $username,
        public ?string $companyName,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $customerNo,
        public ?string $country,
        public ?string $language,
        public int $parentClientId,
        public bool $reseller,
        public bool $locked,
        public bool $canceled,
        public ?CarbonImmutable $createdAt,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'client_id'),
            username: Values::string($data, 'username'),
            companyName: Values::nullableString($data, 'company_name'),
            firstName: Values::nullableString($data, 'contact_firstname'),
            lastName: Values::nullableString($data, 'contact_name'),
            email: Values::nullableString($data, 'email'),
            customerNo: Values::nullableString($data, 'customer_no'),
            country: Values::nullableString($data, 'country'),
            language: Values::nullableString($data, 'language'),
            parentClientId: Values::int($data, 'parent_client_id'),
            reseller: Values::int($data, 'limit_client') !== 0,
            locked: Values::bool($data, 'locked'),
            canceled: Values::bool($data, 'canceled'),
            createdAt: Values::date($data, 'created_at'),
            raw: $data,
        );
    }
}
