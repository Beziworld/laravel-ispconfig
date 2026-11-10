<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig;

use Beziworld\ISPConfig\Resources\Backups;
use Beziworld\ISPConfig\Resources\Clients;
use Beziworld\ISPConfig\Resources\Cron;
use Beziworld\ISPConfig\Resources\Databases;
use Beziworld\ISPConfig\Resources\Dns;
use Beziworld\ISPConfig\Resources\Domains;
use Beziworld\ISPConfig\Resources\Email;
use Beziworld\ISPConfig\Resources\Ftp;
use Beziworld\ISPConfig\Resources\Server;
use Beziworld\ISPConfig\Resources\Sites;

/**
 * Entry point to a single ISPConfig panel, grouping the remote API by topic.
 */
class ISPConfigClient
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function clients(): Clients
    {
        return new Clients($this->connection);
    }

    public function sites(): Sites
    {
        return new Sites($this->connection);
    }

    public function domains(): Domains
    {
        return new Domains($this->connection);
    }

    public function dns(): Dns
    {
        return new Dns($this->connection);
    }

    public function email(): Email
    {
        return new Email($this->connection);
    }

    public function databases(): Databases
    {
        return new Databases($this->connection);
    }

    public function cron(): Cron
    {
        return new Cron($this->connection);
    }

    public function ftp(): Ftp
    {
        return new Ftp($this->connection);
    }

    public function backups(): Backups
    {
        return new Backups($this->connection);
    }

    public function server(): Server
    {
        return new Server($this->connection);
    }

    /**
     * Calls any remote function not covered by the resources; session_id is added automatically.
     *
     * @param array<string, mixed> $parameters Named parameters as on the function page of the API docs.
     */
    public function call(string $function, array $parameters = []): mixed
    {
        return $this->connection->call($function, $parameters);
    }

    /**
     * Ends the remote session of the connection.
     */
    public function logout(): bool
    {
        return $this->connection->logout();
    }
}
