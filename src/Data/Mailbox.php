<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Data;

use Beziworld\ISPConfig\Support\Values;

/**
 * Mailbox (table "mail_user"). The password is never part of API results.
 */
final readonly class Mailbox
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public int $serverId,
        public string $email,
        public string $login,
        public ?string $name,
        public int $quotaBytes,
        public bool $receivesMail,
        public bool $imap,
        public bool $pop3,
        public bool $smtp,
        public bool $autoresponder,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data, 'mailuser_id'),
            serverId: Values::int($data, 'server_id'),
            email: Values::string($data, 'email'),
            login: Values::string($data, 'login', Values::string($data, 'email')),
            name: Values::nullableString($data, 'name'),
            quotaBytes: Values::int($data, 'quota'),
            receivesMail: Values::string($data, 'postfix', 'y') !== 'n',
            imap: ! Values::bool($data, 'disableimap'),
            pop3: ! Values::bool($data, 'disablepop3'),
            smtp: ! Values::bool($data, 'disablesmtp'),
            autoresponder: Values::bool($data, 'autoresponder'),
            raw: $data,
        );
    }

    /**
     * Whether the mailbox has no quota (0 bytes means unlimited).
     */
    public function unlimited(): bool
    {
        return $this->quotaBytes === 0;
    }
}
