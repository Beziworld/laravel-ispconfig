<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\Client;
use Beziworld\ISPConfig\Data\Website;

/**
 * Clients and resellers (Client module).
 */
final class Clients extends Resource
{
    public function get(int $clientId): ?Client
    {
        return $this->find('client_get', 'client_id', $clientId, Client::fromArray(...));
    }

    /**
     * All clients, or the clients matching a filter such as ['company_name' => 'Example%'].
     *
     * @param array<string, scalar> $filter
     * @return list<Client>
     */
    public function all(array $filter = []): array
    {
        return $this->search('client_get', 'client_id', $filter, Client::fromArray(...));
    }

    /**
     * IDs of all clients.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), array_filter($this->array('client_get_all'), is_numeric(...))));
    }

    public function findByUsername(string $username): ?Client
    {
        return $this->all(['username' => $username])[0] ?? null;
    }

    /**
     * Panel user record (sys_user) of a login name: userid, client_id, default_group, groups, ...
     *
     * @return array<array-key, mixed>|null
     */
    public function panelUser(string $username): ?array
    {
        $result = $this->connection->call('client_get_by_username', ['username' => $username]);

        return is_array($result) && $result !== [] ? $result : null;
    }

    public function findByCustomerNo(string $customerNo): ?Client
    {
        return $this->mapList($this->connection->call('client_get_by_customer_no', ['customer_no' => $customerNo]), Client::fromArray(...))[0] ?? null;
    }

    /**
     * Client ID of a panel user (sys_user.userid).
     */
    public function idOfPanelUser(int $sysUserId): ?int
    {
        $id = $this->connection->call('client_get_id', ['sys_userid' => $sysUserId]);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Panel group ID of a client, needed for the "*_by_user" functions and sys_groupid filters.
     */
    public function groupId(int $clientId): ?int
    {
        $id = $this->connection->call('client_get_groupid', ['client_id' => $clientId]);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Creates a client; with "limit_client" other than 0 the record is a reseller.
     *
     * @param array<string, mixed> $params Fields of client_add, e.g. company_name, contact_name, email, username, password, limits.
     * @param int $resellerId Reseller the client belongs to, 0 for the administrator.
     * @return int ID of the new client.
     */
    public function create(array $params, int $resellerId = 0): int
    {
        return $this->insert('client_add', ['reseller_id' => $resellerId, 'params' => $params]);
    }

    /**
     * Changes the given fields; fields that are not sent keep their values.
     *
     * @param array<string, mixed> $params
     * @return int Number of changed records.
     */
    public function update(int $clientId, array $params, int $resellerId = 0): int
    {
        return $this->affected('client_update', ['client_id' => $clientId, 'reseller_id' => $resellerId, 'params' => $params]);
    }

    public function changePassword(int $clientId, #[\SensitiveParameter] string $password): bool
    {
        return $this->affected('client_change_password', ['client_id' => $clientId, 'new_password' => $password]) > 0;
    }

    /**
     * Locks or unlocks the client together with its websites, mail and other services.
     */
    public function lock(int $clientId, bool $locked = true): int
    {
        return $this->update($clientId, ['locked' => $locked ? 'y' : 'n']);
    }

    /**
     * Deletes the client record, its panel login and group. Websites, mail domains and zones stay.
     */
    public function delete(int $clientId): int
    {
        return $this->affected('client_delete', ['client_id' => $clientId]);
    }

    /**
     * Deletes the client together with all its websites, mail, DNS zones, databases and other records.
     */
    public function deleteEverything(int $clientId): int
    {
        return $this->affected('client_delete_everything', ['client_id' => $clientId]);
    }

    /**
     * Client templates (limit templates) defined in the panel.
     *
     * @return list<array<array-key, mixed>>
     */
    public function templates(): array
    {
        return $this->mapList($this->connection->call('client_templates_get_all'), static fn(array $row): array => $row);
    }

    /**
     * Assigns an additional client template and returns the ID of the assignment.
     */
    public function addTemplate(int $clientId, int $templateId): int
    {
        return $this->insert('client_template_additional_add', ['client_id' => $clientId, 'template_id' => $templateId]);
    }

    public function removeTemplate(int $clientId, int $assignedTemplateId): int
    {
        return $this->affected('client_template_additional_delete', ['client_id' => $clientId, 'assigned_template_id' => $assignedTemplateId]);
    }

    /**
     * Websites (vhost) a panel user or group may read.
     *
     * @param int|string $sysGroupIds One group ID or several separated by commas.
     * @return list<Website>
     */
    public function websites(int $sysUserId, int|string $sysGroupIds): array
    {
        return $this->mapList(
            $this->connection->call('client_get_sites_by_user', ['sys_userid' => $sysUserId, 'sys_groupid' => (string) $sysGroupIds]),
            Website::fromArray(...),
        );
    }

    /**
     * Checks the panel login of a client or mail user and returns the matching record, or null.
     *
     * @return array<array-key, mixed>|null
     */
    public function checkLogin(string $username, #[\SensitiveParameter] string $password, string $remoteIp = ''): ?array
    {
        $result = $this->connection->call('client_login_get', ['username' => $username, 'password' => $password, 'remote_ip' => $remoteIp]);

        return is_array($result) && $result !== [] ? $result : null;
    }
}
