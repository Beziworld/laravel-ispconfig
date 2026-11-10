<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

/**
 * Domain module (Clients > Domains): the domains a client may use for websites, mail and DNS.
 */
final class Domains extends Resource
{
    /**
     * @return array<array-key, mixed>|null
     */
    public function get(int $domainId): ?array
    {
        return $this->find('domains_domain_get', 'primary_id', $domainId, static fn(array $row): array => $row);
    }

    /**
     * All domains, or the domains matching a filter such as ['domain' => '%.example.com'].
     *
     * @param array<string, scalar> $filter
     * @return list<array<array-key, mixed>>
     */
    public function all(array $filter = []): array
    {
        return $this->search('domains_domain_get', 'primary_id', $filter, static fn(array $row): array => $row);
    }

    /**
     * Domains of a panel group (see Clients::groupId()), each with domain_id and domain.
     *
     * @return list<array<array-key, mixed>>
     */
    public function ofGroup(int $groupId): array
    {
        return $this->mapList($this->connection->call('domains_get_all_by_user', ['group_id' => $groupId]), static fn(array $row): array => $row);
    }

    /**
     * Assigns a domain to a client (0: the administrator) and returns its ID.
     */
    public function create(int $clientId, string $domain): int
    {
        return $this->insert('domains_domain_add', ['client_id' => $clientId, 'params' => ['domain' => $domain]]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function update(int $clientId, int $domainId, array $params): int
    {
        return $this->affected('domains_domain_update', ['client_id' => $clientId, 'primary_id' => $domainId, 'params' => $params]);
    }

    public function delete(int $domainId): int
    {
        return $this->affected('domains_domain_delete', ['primary_id' => $domainId]);
    }
}
