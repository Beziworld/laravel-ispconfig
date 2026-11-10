<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Support;

use Carbon\CarbonImmutable;

/**
 * Conversions of loosely typed API values into strict PHP types.
 *
 * @internal
 */
final class Values
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Interprets 1/0, "1"/"0", "y"/"n" and booleans.
     *
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value) && in_array(strtolower((string) $value), ['1', 'y', 'yes', 'true', 'on'], true);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * Returns the list items of a value that are arrays.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /**
     * Parses a Unix timestamp or a "Y-m-d H:i:s" value; dates without a zone are read in the given time zone.
     *
     * @param array<array-key, mixed> $data
     */
    public static function date(array $data, string $key, string $timezone = 'UTC'): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if (is_int($value) || (is_string($value) && preg_match('/^\d{9,11}$/', $value) === 1)) {
            return (int) $value > 0 ? CarbonImmutable::createFromTimestamp((int) $value, $timezone) : null;
        }

        if (! is_string($value) || trim($value) === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Converts a PHP boolean into the "y"/"n" flag used by most ISPConfig fields.
     */
    public static function yesNo(bool $value, bool $upper = false): string
    {
        $flag = $value ? 'y' : 'n';

        return $upper ? strtoupper($flag) : $flag;
    }
}
