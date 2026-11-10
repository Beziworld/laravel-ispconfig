<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Server of a single or multi-server ISPConfig setup.
 */
final readonly class Server
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'server_id'),
            name: Values::string($data, 'server_name'),
            raw: $data,
        );
    }
}
