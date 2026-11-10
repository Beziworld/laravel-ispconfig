<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Connection;
use Beziworld\ISPConfig\Exceptions\ApiException;

/**
 * Base class of API areas grouped by topic (clients, websites, DNS, mail, ...).
 *
 * Most ISPConfig objects share the same five functions: <prefix>_get,
 * <prefix>_add, <prefix>_update and <prefix>_delete, where _get accepts an ID,
 * -1 for all records or an array filter (a value with % is compared with LIKE,
 * #OFFSET# and #LIMIT# page the result).
 */
abstract class Resource
{
    public function __construct(
        protected readonly Connection $connection,
    ) {}

    /**
     * Reads a single record by ID and maps it with the given factory; null when it does not exist.
     *
     * @template T
     *
     * @param callable(array<array-key, mixed>): T $map
     * @return T|null
     */
    protected function find(string $function, string $idParameter, int $id, callable $map): mixed
    {
        $record = $this->connection->call($function, [$idParameter => $id]);

        return is_array($record) && $record !== [] && ! array_is_list($record) ? $map($record) : null;
    }

    /**
     * Reads all records (-1) or the records matching a filter.
     *
     * @template T
     *
     * @param array<string, scalar> $filter
     * @param callable(array<array-key, mixed>): T $map
     * @return list<T>
     */
    protected function search(string $function, string $idParameter, array $filter, callable $map): array
    {
        return $this->mapList($this->connection->call($function, [$idParameter => $filter === [] ? -1 : $filter]), $map);
    }

    /**
     * @template T
     *
     * @param callable(array<array-key, mixed>): T $map
     * @return list<T>
     */
    protected function mapList(mixed $records, callable $map): array
    {
        if (! is_array($records)) {
            return [];
        }

        if ($records !== [] && ! array_is_list($records) && ! is_array(reset($records))) {
            $records = [$records];
        }

        $items = [];

        foreach ($records as $record) {
            if (is_array($record)) {
                $items[] = $map($record);
            }
        }

        return $items;
    }

    /**
     * Calls an _add function and returns the ID of the new record.
     *
     * @param array<string, mixed> $parameters
     */
    protected function insert(string $function, array $parameters): int
    {
        $id = $this->connection->call($function, $parameters);

        if (! is_numeric($id) || (int) $id <= 0) {
            throw ApiException::unexpectedResponse($function, 200);
        }

        return (int) $id;
    }

    /**
     * Calls an _update, _delete or _set_status function and returns the number of affected records.
     *
     * @param array<string, mixed> $parameters
     */
    protected function affected(string $function, array $parameters): int
    {
        $result = $this->connection->call($function, $parameters);

        return match (true) {
            is_numeric($result) => (int) $result,
            $result === true => 1,
            default => 0,
        };
    }

    /**
     * Calls a function that answers with true when an action was queued.
     *
     * @param array<string, mixed> $parameters
     */
    protected function confirmed(string $function, array $parameters): bool
    {
        $result = $this->connection->call($function, $parameters);

        return $result === true || $result === 1 || $result === '1';
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<array-key, mixed>
     */
    protected function array(string $function, array $parameters = []): array
    {
        $result = $this->connection->call($function, $parameters);

        return is_array($result) ? $result : [];
    }

    protected static function status(bool $active): string
    {
        return $active ? 'active' : 'inactive';
    }
}
