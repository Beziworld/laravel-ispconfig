<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\Mailbox;
use Beziworld\ISPConfig\Data\MailDomain;
use Beziworld\ISPConfig\Data\MailForwarding;

/**
 * Mail domains, mailboxes, aliases, forwards and catchalls (Email module).
 */
final class Email extends Resource
{
    public function domain(int $domainId): ?MailDomain
    {
        return $this->find('mail_domain_get', 'primary_id', $domainId, MailDomain::fromArray(...));
    }

    /**
     * All mail domains, or the domains matching a filter.
     *
     * @param array<string, scalar> $filter
     * @return list<MailDomain>
     */
    public function domains(array $filter = []): array
    {
        return $this->search('mail_domain_get', 'primary_id', $filter, MailDomain::fromArray(...));
    }

    public function findDomain(string $domain): ?MailDomain
    {
        return $this->mapList($this->connection->call('mail_domain_get_by_domain', ['domain' => $domain]), MailDomain::fromArray(...))[0] ?? null;
    }

    /**
     * Creates a mail domain.
     *
     * @param array<string, mixed> $params Additional fields: dkim, dkim_selector, dkim_private, relay_host, local_delivery, ...
     */
    public function createDomain(int $clientId, int $serverId, string $domain, array $params = []): int
    {
        return $this->insert('mail_domain_add', [
            'client_id' => $clientId,
            'params' => array_replace(['server_id' => $serverId, 'domain' => $domain, 'active' => 'y'], $params),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateDomain(int $clientId, int $domainId, array $params): int
    {
        return $this->affected('mail_domain_update', ['client_id' => $clientId, 'primary_id' => $domainId, 'params' => $params]);
    }

    /**
     * Deletes a mail domain with its mailboxes, aliases, forwards and mailing lists.
     */
    public function deleteDomain(int $domainId): int
    {
        return $this->affected('mail_domain_delete', ['primary_id' => $domainId]);
    }

    public function setDomainActive(int $domainId, bool $active): bool
    {
        return $this->affected('mail_domain_set_status', ['primary_id' => $domainId, 'status' => self::status($active)]) > 0;
    }

    public function mailbox(int $mailboxId): ?Mailbox
    {
        return $this->find('mail_user_get', 'primary_id', $mailboxId, Mailbox::fromArray(...));
    }

    /**
     * All mailboxes, or the mailboxes matching a filter such as ['email' => '%@example.com'].
     *
     * @param array<string, scalar> $filter
     * @return list<Mailbox>
     */
    public function mailboxes(array $filter = []): array
    {
        return $this->search('mail_user_get', 'primary_id', $filter, Mailbox::fromArray(...));
    }

    public function findMailbox(string $email): ?Mailbox
    {
        return $this->mailboxes(['email' => $email])[0] ?? null;
    }

    /**
     * @return list<Mailbox>
     */
    public function mailboxesOfClient(int $clientId): array
    {
        return $this->mapList($this->connection->call('mail_user_get_all_by_client', ['client_id' => $clientId]), Mailbox::fromArray(...));
    }

    /**
     * Creates a mailbox; the login defaults to the address.
     *
     * @param int $quotaMb Quota in MB, 0 for unlimited (ISPConfig stores bytes).
     * @param array<string, mixed> $params Additional fields: name, cc, autoresponder, move_junk, disableimap, ...
     */
    public function createMailbox(
        int $clientId,
        int $serverId,
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $quotaMb = 0,
        array $params = [],
    ): int {
        return $this->insert('mail_user_add', [
            'client_id' => $clientId,
            'params' => array_replace([
                'server_id' => $serverId,
                'email' => $email,
                'login' => $email,
                'password' => $password,
                'quota' => $quotaMb * 1024 * 1024,
                'postfix' => 'y',
            ], $params),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateMailbox(int $clientId, int $mailboxId, array $params): int
    {
        return $this->affected('mail_user_update', ['client_id' => $clientId, 'primary_id' => $mailboxId, 'params' => $params]);
    }

    public function changeMailboxPassword(int $clientId, int $mailboxId, #[\SensitiveParameter] string $password): int
    {
        return $this->updateMailbox($clientId, $mailboxId, ['password' => $password]);
    }

    /**
     * Sets the quota of a mailbox in MB (0: unlimited).
     */
    public function changeMailboxQuota(int $clientId, int $mailboxId, int $quotaMb): int
    {
        return $this->updateMailbox($clientId, $mailboxId, ['quota' => $quotaMb * 1024 * 1024]);
    }

    /**
     * Deletes a mailbox with its spamfilter user, filters and fetchmail accounts.
     */
    public function deleteMailbox(int $mailboxId): int
    {
        return $this->affected('mail_user_delete', ['primary_id' => $mailboxId]);
    }

    /**
     * Mail usage of the mailboxes of a client (used_raw and quota_raw in bytes, used_percentage).
     *
     * @return list<array<array-key, mixed>>
     */
    public function quota(int $clientId): array
    {
        return $this->mapList($this->connection->call('mailquota_get_by_user', ['client_id' => $clientId]), static fn(array $row): array => $row);
    }

    /**
     * Adds an alias: mail to $source is delivered to the mailbox $destination.
     */
    public function createAlias(int $clientId, int $serverId, string $source, string $destination, bool $allowSendAs = true): int
    {
        return $this->insertForwarding('mail_alias_add', 'alias', $clientId, $serverId, $source, $destination, $allowSendAs);
    }

    /**
     * Adds a forward: mail to $source is sent on to one or more external addresses.
     *
     * @param string|list<string> $destinations
     */
    public function createForward(int $clientId, int $serverId, string $source, string|array $destinations, bool $allowSendAs = false): int
    {
        return $this->insertForwarding('mail_forward_add', 'forward', $clientId, $serverId, $source, implode(',', (array) $destinations), $allowSendAs);
    }

    /**
     * Adds a catchall: mail to unknown addresses of the domain goes to $destination.
     */
    public function createCatchall(int $clientId, int $serverId, string $domain, string $destination): int
    {
        return $this->insertForwarding('mail_catchall_add', 'catchall', $clientId, $serverId, '@' . ltrim($domain, '@'), $destination, false);
    }

    /**
     * @param array<string, scalar> $filter
     * @return list<MailForwarding>
     */
    public function aliases(array $filter = []): array
    {
        return $this->search('mail_alias_get', 'primary_id', $filter, MailForwarding::fromArray(...));
    }

    /**
     * @param array<string, scalar> $filter
     * @return list<MailForwarding>
     */
    public function forwards(array $filter = []): array
    {
        return $this->search('mail_forward_get', 'primary_id', $filter, MailForwarding::fromArray(...));
    }

    /**
     * @param array<string, scalar> $filter
     * @return list<MailForwarding>
     */
    public function catchalls(array $filter = []): array
    {
        return $this->search('mail_catchall_get', 'primary_id', $filter, MailForwarding::fromArray(...));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateAlias(int $clientId, int $aliasId, array $params): int
    {
        return $this->affected('mail_alias_update', ['client_id' => $clientId, 'primary_id' => $aliasId, 'params' => $params]);
    }

    public function deleteAlias(int $aliasId): int
    {
        return $this->affected('mail_alias_delete', ['primary_id' => $aliasId]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateForward(int $clientId, int $forwardId, array $params): int
    {
        return $this->affected('mail_forward_update', ['client_id' => $clientId, 'primary_id' => $forwardId, 'params' => $params]);
    }

    public function deleteForward(int $forwardId): int
    {
        return $this->affected('mail_forward_delete', ['primary_id' => $forwardId]);
    }

    public function deleteCatchall(int $catchallId): int
    {
        return $this->affected('mail_catchall_delete', ['primary_id' => $catchallId]);
    }

    private function insertForwarding(
        string $function,
        string $type,
        int $clientId,
        int $serverId,
        string $source,
        string $destination,
        bool $allowSendAs,
    ): int {
        return $this->insert($function, [
            'client_id' => $clientId,
            'params' => [
                'server_id' => $serverId,
                'source' => $source,
                'destination' => $destination,
                'type' => $type,
                'allow_send_as' => $allowSendAs ? 'y' : 'n',
                'active' => 'y',
            ],
        ]);
    }
}
