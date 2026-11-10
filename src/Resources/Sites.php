<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\Website;

/**
 * Websites with their subdomains and alias domains (Sites module).
 */
final class Sites extends Resource
{
    public function get(int $websiteId): ?Website
    {
        return $this->find('sites_web_domain_get', 'primary_id', $websiteId, Website::fromArray(...));
    }

    /**
     * All websites, or the websites matching a filter such as ['domain' => 'example.com'].
     *
     * @param array<string, scalar> $filter
     * @return list<Website>
     */
    public function all(array $filter = []): array
    {
        return $this->search('sites_web_domain_get', 'primary_id', $filter, Website::fromArray(...));
    }

    public function findByDomain(string $domain): ?Website
    {
        return $this->all(['domain' => $domain])[0] ?? null;
    }

    /**
     * Creates a website.
     *
     * @param array<string, mixed> $params Fields of sites_web_domain_add: server_id, ip_address, domain, hd_quota, traffic_quota, php, ssl, ssl_letsencrypt, active, ...
     * @param bool $readonly The client can not change the website in the panel.
     * @return int ID of the new website.
     */
    public function create(int $clientId, array $params, bool $readonly = false): int
    {
        return $this->insert('sites_web_domain_add', [
            'client_id' => $clientId,
            'params' => $params + ['type' => 'vhost', 'vhost_type' => 'name'],
            'readonly' => $readonly,
        ]);
    }

    /**
     * Changes the given fields; fields that are not sent keep their values.
     *
     * @param array<string, mixed> $params
     */
    public function update(int $clientId, int $websiteId, array $params): int
    {
        return $this->affected('sites_web_domain_update', ['client_id' => $clientId, 'primary_id' => $websiteId, 'params' => $params]);
    }

    public function delete(int $websiteId): int
    {
        return $this->affected('sites_web_domain_delete', ['primary_id' => $websiteId]);
    }

    /**
     * Enables or disables a website. Returns false when it already had that status.
     */
    public function setActive(int $websiteId, bool $active): bool
    {
        return $this->affected('sites_web_domain_set_status', ['primary_id' => $websiteId, 'status' => self::status($active)]) > 0;
    }

    /**
     * Requests a Let's Encrypt certificate by switching SSL and Let's Encrypt on.
     *
     * The certificate is issued by the server with the next configuration run; the DNS
     * records of the domain must already point to the server.
     */
    public function enableLetsEncrypt(int $clientId, int $websiteId): int
    {
        return $this->update($clientId, $websiteId, ['ssl' => 'y', 'ssl_letsencrypt' => 'y']);
    }

    /**
     * Adds an alias domain (a domain served with the content of the website).
     *
     * @param array<string, mixed> $params Additional fields, e.g. redirect_type, redirect_path, subdomain.
     */
    public function createAliasDomain(int $clientId, int $websiteId, string $domain, array $params = []): int
    {
        return $this->insert('sites_web_aliasdomain_add', [
            'client_id' => $clientId,
            'params' => array_replace(['parent_domain_id' => $websiteId, 'domain' => $domain, 'active' => 'y'], $params),
        ]);
    }

    public function deleteAliasDomain(int $aliasDomainId): int
    {
        return $this->affected('sites_web_aliasdomain_delete', ['primary_id' => $aliasDomainId]);
    }

    /**
     * Adds a subdomain of a website (served with the content of the website).
     *
     * @param array<string, mixed> $params Additional fields, e.g. redirect_type, redirect_path.
     */
    public function createSubdomain(int $clientId, int $websiteId, string $domain, array $params = []): int
    {
        return $this->insert('sites_web_subdomain_add', [
            'client_id' => $clientId,
            'params' => array_replace(['parent_domain_id' => $websiteId, 'domain' => $domain, 'active' => 'y'], $params),
        ]);
    }

    public function deleteSubdomain(int $subdomainId): int
    {
        return $this->affected('sites_web_subdomain_delete', ['primary_id' => $subdomainId]);
    }

    /**
     * Adds a subdomain with its own document root (vhost subdomain).
     *
     * @param array<string, mixed> $params Fields of sites_web_vhost_subdomain_add.
     */
    public function createVhostSubdomain(int $clientId, array $params): int
    {
        return $this->insert('sites_web_vhost_subdomain_add', ['client_id' => $clientId, 'params' => $params]);
    }

    public function deleteVhostSubdomain(int $subdomainId): int
    {
        return $this->affected('sites_web_vhost_subdomain_delete', ['primary_id' => $subdomainId]);
    }

    /**
     * Disk usage of the websites of a client (used, soft and hard limits in KB, *_raw in bytes).
     *
     * @return list<array<array-key, mixed>>
     */
    public function quota(int $clientId): array
    {
        return $this->mapList($this->connection->call('quota_get_by_user', ['client_id' => $clientId]), static fn(array $row): array => $row);
    }

    /**
     * Web traffic of the websites of a client in bytes (this_month, last_month, this_year, last_year).
     *
     * @return array<array-key, mixed> Keyed by domain.
     */
    public function traffic(int $clientId, int $lastDays = 0): array
    {
        return $this->array('trafficquota_get_by_user', ['client_id' => $clientId, 'lastdays' => $lastDays]);
    }
}
