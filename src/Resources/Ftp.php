<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\FtpUser;
use Beziworld\ISPConfig\Data\Website;
use InvalidArgumentException;

/**
 * FTP users of websites.
 */
final class Ftp extends Resource
{
    public function get(int $ftpUserId): ?FtpUser
    {
        return $this->find('sites_ftp_user_get', 'primary_id', $ftpUserId, FtpUser::fromArray(...));
    }

    /**
     * @param array<string, scalar> $filter
     * @return list<FtpUser>
     */
    public function all(array $filter = []): array
    {
        return $this->search('sites_ftp_user_get', 'primary_id', $filter, FtpUser::fromArray(...));
    }

    /**
     * Creates an FTP user for a website.
     *
     * The system user, group and document root are taken from the website record,
     * which therefore has to be passed as read with Sites::get().
     *
     * @param string|null $directory Document root of the website (default) or a folder below it.
     * @param array<string, mixed> $params Additional fields: quota_files, ul_ratio, dl_ratio, ul_bandwidth, dl_bandwidth.
     */
    public function create(
        int $clientId,
        Website $website,
        string $username,
        #[\SensitiveParameter]
        string $password,
        ?string $directory = null,
        int $quotaMb = -1,
        array $params = [],
    ): int {
        if ($website->systemUser === null || $website->systemGroup === null || $website->documentRoot === null) {
            throw new InvalidArgumentException(sprintf('Website %d has no system user, group or document root.', $website->id));
        }

        return $this->insert('sites_ftp_user_add', [
            'client_id' => $clientId,
            'params' => array_replace([
                'server_id' => $website->serverId,
                'parent_domain_id' => $website->id,
                'username' => $username,
                'password' => $password,
                'quota_size' => $quotaMb,
                'uid' => $website->systemUser,
                'gid' => $website->systemGroup,
                'dir' => $directory ?? $website->documentRoot,
                'active' => 'y',
            ], $params),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function update(int $clientId, int $ftpUserId, array $params): int
    {
        return $this->affected('sites_ftp_user_update', ['client_id' => $clientId, 'primary_id' => $ftpUserId, 'params' => $params]);
    }

    public function changePassword(int $clientId, int $ftpUserId, #[\SensitiveParameter] string $password): int
    {
        return $this->update($clientId, $ftpUserId, ['password' => $password]);
    }

    public function delete(int $ftpUserId): int
    {
        return $this->affected('sites_ftp_user_delete', ['primary_id' => $ftpUserId]);
    }

    /**
     * The "server" configuration (hostname, ip_address, ...) of the server an FTP user lives on.
     *
     * @return array<array-key, mixed>|null
     */
    public function server(string $username): ?array
    {
        $result = $this->connection->call('sites_ftp_user_server_get', ['ftp_user' => $username]);

        return is_array($result) && $result !== [] ? $result : null;
    }
}
