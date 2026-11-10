<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\DnsRecord;
use Beziworld\ISPConfig\Data\DnsZone;
use Beziworld\ISPConfig\Exceptions\ApiException;
use InvalidArgumentException;

/**
 * Primary DNS zones and their records (DNS module).
 *
 * Record functions exist per type (dns_a_add, dns_mx_update, dns_txt_delete,
 * ...); the type is passed as the first argument of the record methods.
 * Record changes increase the serial of the zone unless $updateSerial is false.
 */
final class Dns extends Resource
{
    /**
     * Record types with their own remote functions.
     */
    public const array RECORD_TYPES = [
        'A', 'AAAA', 'ALIAS', 'CAA', 'CNAME', 'DNAME', 'DS', 'HINFO', 'LOC', 'MX', 'NAPTR', 'NS', 'PTR', 'RP', 'SRV',
        'SSHFP', 'TLSA', 'TXT',
    ];

    public function zone(int $zoneId): ?DnsZone
    {
        return $this->find('dns_zone_get', 'primary_id', $zoneId, DnsZone::fromArray(...));
    }

    /**
     * All primary zones, or the zones matching a filter such as ['origin' => 'example.com.'].
     *
     * @param array<string, scalar> $filter
     * @return list<DnsZone>
     */
    public function zones(array $filter = []): array
    {
        return $this->search('dns_zone_get', 'primary_id', $filter, DnsZone::fromArray(...));
    }

    /**
     * Finds a zone by its name, with or without the trailing dot.
     */
    public function findZone(string $domain): ?DnsZone
    {
        return $this->zones(['origin' => rtrim($domain, '.') . '.'])[0] ?? null;
    }

    /**
     * ID of the zone with the given name, or null when there is none.
     */
    public function zoneId(string $domain): ?int
    {
        try {
            $id = $this->connection->call('dns_zone_get_id', ['origin' => rtrim($domain, '.')]);
        } catch (ApiException $exception) {
            if ($exception->faultCode() === 'no_domain_found') {
                return null;
            }

            throw $exception;
        }

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Zones of a client on a DNS server (each with id and origin).
     *
     * @return list<array<array-key, mixed>>
     */
    public function zonesOfClient(int $clientId, int $serverId): array
    {
        return $this->mapList($this->connection->call('dns_zone_get_by_user', ['client_id' => $clientId, 'server_id' => $serverId]), static fn(array $row): array => $row);
    }

    /**
     * Creates an empty primary zone.
     *
     * @param array<string, mixed> $params Fields of dns_zone_add: server_id, origin, ns, mbox, serial, refresh, retry, expire, minimum, ttl, active, ...
     */
    public function createZone(int $clientId, array $params): int
    {
        if (isset($params['origin']) && is_string($params['origin'])) {
            $params['origin'] = rtrim($params['origin'], '.') . '.';
        }

        return $this->insert('dns_zone_add', ['client_id' => $clientId, 'params' => $params + ['active' => 'Y']]);
    }

    /**
     * Creates a zone with the records of a DNS template, like the DNS wizard of the panel.
     */
    public function createZoneFromTemplate(
        int $clientId,
        string $domain,
        string $ip,
        string $ns1,
        string $ns2,
        string $email,
        int $templateId = 1,
        string $ipv6 = '',
    ): bool {
        return $this->confirmed('dns_templatezone_add', [
            'client_id' => $clientId,
            'template_id' => $templateId,
            'domain' => rtrim($domain, '.'),
            'ip' => $ip,
            'ns1' => rtrim($ns1, '.'),
            'ns2' => rtrim($ns2, '.'),
            'email' => $email,
            'ipv6' => $ipv6,
        ]);
    }

    /**
     * DNS templates of the wizard.
     *
     * @return list<array<array-key, mixed>>
     */
    public function templates(): array
    {
        return $this->mapList($this->connection->call('dns_templatezone_get_all'), static fn(array $row): array => $row);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateZone(int $clientId, int $zoneId, array $params): int
    {
        return $this->affected('dns_zone_update', ['client_id' => $clientId, 'primary_id' => $zoneId, 'params' => $params]);
    }

    public function deleteZone(int $zoneId): int
    {
        return $this->affected('dns_zone_delete', ['primary_id' => $zoneId]);
    }

    public function setZoneActive(int $zoneId, bool $active): bool
    {
        return $this->affected('dns_zone_set_status', ['primary_id' => $zoneId, 'status' => self::status($active)]) > 0;
    }

    /**
     * All records of a zone, of every type.
     *
     * @return list<DnsRecord>
     */
    public function records(int $zoneId): array
    {
        return $this->mapList($this->connection->call('dns_rr_get_all_by_zone', ['zone_id' => $zoneId]), DnsRecord::fromArray(...));
    }

    public function record(string $type, int $recordId): ?DnsRecord
    {
        return $this->find(self::function($type, 'get'), 'primary_id', $recordId, DnsRecord::fromArray(...));
    }

    /**
     * Adds a record and returns its ID.
     *
     * @param string $name Relative to the zone ("www", "" or "@" for the apex) or a full name with a trailing dot.
     * @param string $data Address, target host or text, e.g. "192.0.2.10" or "v=spf1 mx -all".
     * @param array<string, mixed> $params Additional fields, e.g. ['aux' => 10] for the MX priority.
     */
    public function createRecord(
        int $clientId,
        int $zoneId,
        string $type,
        string $name,
        string $data,
        int $ttl = 3600,
        array $params = [],
        bool $updateSerial = true,
    ): int {
        return $this->insert(self::function($type, 'add'), [
            'client_id' => $clientId,
            'params' => array_replace(['zone' => $zoneId, 'name' => $name === '@' ? '' : $name, 'data' => $data, 'ttl' => $ttl, 'active' => 'Y'], $params),
            'update_serial' => $updateSerial,
        ]);
    }

    /**
     * Changes the given fields of a record (name, data, aux, ttl, active).
     *
     * @param array<string, mixed> $params
     */
    public function updateRecord(int $clientId, string $type, int $recordId, array $params, bool $updateSerial = true): int
    {
        return $this->affected(self::function($type, 'update'), [
            'client_id' => $clientId,
            'primary_id' => $recordId,
            'params' => $params,
            'update_serial' => $updateSerial,
        ]);
    }

    public function deleteRecord(string $type, int $recordId, bool $updateSerial = true): int
    {
        return $this->affected(self::function($type, 'delete'), ['primary_id' => $recordId, 'update_serial' => $updateSerial]);
    }

    /**
     * Name of the remote function for a record type and operation, e.g. ("MX", "add") gives "dns_mx_add".
     */
    public static function function(string $type, string $operation): string
    {
        $type = strtoupper(trim($type));

        if (! in_array($type, self::RECORD_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported DNS record type "%s".', $type));
        }

        return 'dns_' . strtolower($type) . '_' . $operation;
    }
}
