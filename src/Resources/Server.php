<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\Server as ServerData;
use Beziworld\ISPConfig\Support\Values;

/**
 * Servers of the setup, versions and the job queue (System module).
 */
final class Server extends Resource
{
    /**
     * @return list<ServerData>
     */
    public function all(): array
    {
        return $this->mapList($this->connection->call('server_get_all'), ServerData::fromArray(...));
    }

    /**
     * Configuration of a server, or one section of it (e.g. "server", "web", "mail").
     *
     * @return array<array-key, mixed>
     */
    public function config(int $serverId, string $section = ''): array
    {
        return $this->array('server_get', ['server_id' => $serverId, 'section' => $section]);
    }

    public function idByName(string $serverName): ?int
    {
        return self::firstId($this->connection->call('server_get_serverid_by_name', ['server_name' => $serverName]));
    }

    public function idByIp(string $ipAddress): ?int
    {
        return self::firstId($this->connection->call('server_get_serverid_by_ip', ['ipaddress' => $ipAddress]));
    }

    /**
     * Services of a server: mail_server, web_server, dns_server, file_server, db_server, ... (1: on).
     *
     * @return array<array-key, mixed>
     */
    public function functions(int $serverId): array
    {
        $result = $this->array('server_get_functions', ['server_id' => $serverId]);

        return isset($result[0]) && is_array($result[0]) ? $result[0] : $result;
    }

    /**
     * ISPConfig version of the panel (server 0) or of a server.
     */
    public function version(int $serverId = 0): ?string
    {
        return Values::nullableString($this->array('server_get_app_version', ['server_id' => $serverId]), 'ispc_app_version');
    }

    /**
     * Additional PHP versions of a server for a PHP mode ("php-fpm" or "fast-cgi").
     *
     * @return array<array-key, mixed>
     */
    public function phpVersions(int $serverId, string $php = 'php-fpm', bool $fullData = false): array
    {
        return $this->array('server_get_php_versions', ['server_id' => $serverId, 'php' => $php, 'get_full_data' => $fullData]);
    }

    /**
     * Number of changes that the servers have not processed yet (all servers for 0).
     */
    public function pendingJobs(int $serverId = 0): int
    {
        $count = $this->connection->call('monitor_jobqueue_count', ['server_id' => $serverId]);

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Names of all functions of the remote API.
     *
     * @return list<string>
     */
    public function functionList(): array
    {
        return array_values(array_filter($this->array('get_function_list'), is_string(...)));
    }

    private static function firstId(mixed $result): ?int
    {
        if (is_array($result)) {
            $first = reset($result);
            $result = is_array($first) ? ($first['server_id'] ?? null) : ($result['server_id'] ?? $first);
        }

        return is_numeric($result) && (int) $result > 0 ? (int) $result : null;
    }
}
