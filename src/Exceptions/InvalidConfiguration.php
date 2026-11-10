<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Exceptions;

/**
 * Raised when a connection is not configured or incomplete.
 */
final class InvalidConfiguration extends ISPConfigException
{
    public static function missing(string $connection, string $key, ?string $env = null): self
    {
        return new self(sprintf(
            'ISPConfig connection "%s" has no "%s" configured%s.',
            $connection,
            $key,
            $env !== null && $connection === 'default' ? ' (set ' . $env . ')' : '',
        ));
    }

    public static function unknownConnection(string $connection): self
    {
        return new self(sprintf('ISPConfig connection "%s" is not defined in config/ispconfig.php.', $connection));
    }
}
