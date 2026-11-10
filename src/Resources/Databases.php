<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\Database;
use Beziworld\ISPConfig\Data\DatabaseUser;

/**
 * Databases and database users of websites.
 *
 * The remote API does not add the database name prefix configured in the
 * panel: names are stored exactly as sent.
 */
final class Databases extends Resource
{
    public function get(int $databaseId): ?Database
    {
        return $this->find('sites_database_get', 'primary_id', $databaseId, Database::fromArray(...));
    }

    /**
     * @param array<string, scalar> $filter
     * @return list<Database>
     */
    public function all(array $filter = []): array
    {
        return $this->search('sites_database_get', 'primary_id', $filter, Database::fromArray(...));
    }

    /**
     * Databases of a client with the name of their read/write user.
     *
     * @return list<Database>
     */
    public function ofClient(int $clientId): array
    {
        return $this->mapList($this->connection->call('sites_database_get_all_by_user', ['client_id' => $clientId]), Database::fromArray(...));
    }

    /**
     * Creates a database of a website; the user must exist (see createUser()).
     *
     * @param array<string, mixed> $params Additional fields: type, database_ro_user_id, database_quota, database_charset, remote_access, remote_ips, backup_interval, ...
     */
    public function create(int $clientId, int $serverId, int $websiteId, string $name, int $userId, array $params = []): int
    {
        return $this->insert('sites_database_add', [
            'client_id' => $clientId,
            'params' => array_replace([
                'server_id' => $serverId,
                'parent_domain_id' => $websiteId,
                'type' => 'mysql',
                'database_name' => $name,
                'database_user_id' => $userId,
                'database_ro_user_id' => 0,
                'database_charset' => 'utf8mb4',
                'remote_access' => 'n',
                'active' => 'y',
            ], $params),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function update(int $clientId, int $databaseId, array $params): int
    {
        return $this->affected('sites_database_update', ['client_id' => $clientId, 'primary_id' => $databaseId, 'params' => $params]);
    }

    public function delete(int $databaseId): int
    {
        return $this->affected('sites_database_delete', ['primary_id' => $databaseId]);
    }

    public function user(int $userId): ?DatabaseUser
    {
        return $this->find('sites_database_user_get', 'primary_id', $userId, DatabaseUser::fromArray(...));
    }

    /**
     * @param array<string, scalar> $filter
     * @return list<DatabaseUser>
     */
    public function users(array $filter = []): array
    {
        return $this->search('sites_database_user_get', 'primary_id', $filter, DatabaseUser::fromArray(...));
    }

    /**
     * Creates a database user; server 0 creates it on every database server, like the panel.
     */
    public function createUser(int $clientId, string $username, #[\SensitiveParameter] string $password, int $serverId = 0): int
    {
        return $this->insert('sites_database_user_add', [
            'client_id' => $clientId,
            'params' => ['server_id' => $serverId, 'database_user' => $username, 'database_password' => $password],
        ]);
    }

    public function changeUserPassword(int $clientId, int $userId, #[\SensitiveParameter] string $password): int
    {
        return $this->affected('sites_database_user_update', [
            'client_id' => $clientId,
            'primary_id' => $userId,
            'params' => ['database_password' => $password],
        ]);
    }

    public function deleteUser(int $userId): int
    {
        return $this->affected('sites_database_user_delete', ['primary_id' => $userId]);
    }

    /**
     * Disk usage of the databases of a client.
     *
     * @return list<array<array-key, mixed>>
     */
    public function quota(int $clientId): array
    {
        return $this->mapList($this->connection->call('databasequota_get_by_user', ['client_id' => $clientId]), static fn(array $row): array => $row);
    }
}
