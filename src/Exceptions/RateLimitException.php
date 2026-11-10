<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Exceptions;

/**
 * Raised when a proxy in front of the panel answers with HTTP 429.
 */
final class RateLimitException extends ISPConfigException
{
    public function __construct(
        string $message,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, 429);
    }

    public static function make(string $function, ?int $retryAfter): self
    {
        return new self(
            sprintf('ISPConfig %s was rate limited (HTTP 429)%s.', $function, $retryAfter !== null ? ', retry after ' . $retryAfter . ' s' : ''),
            $retryAfter,
        );
    }

    /**
     * Seconds to wait before the next request, from the Retry-After header.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
