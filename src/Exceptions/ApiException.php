<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Exceptions;

use Throwable;

/**
 * Raised when the remote API reports an error or cannot be reached.
 *
 * The JSON endpoint returns every SOAP fault of a function as the code
 * "remote_fault" with the fault string only. faultCode() restores the
 * original fault code (e.g. "permission_denied") for the fixed messages of
 * the ISPConfig remoting library; validation errors of add and update
 * functions are reported as "data_processing_error".
 */
class ApiException extends ISPConfigException
{
    /**
     * Fault strings of the ISPConfig remoting library mapped to their SOAP fault codes.
     */
    public const array FAULTS = [
        'The login failed. Username or password wrong.' => 'login_failed',
        'The login failure limit has been reached.' => 'login_failure_limit',
        'The login username is empty.' => 'login_username_empty',
        'The login password is empty.' => 'login_password_empty',
        'The login failed. Client logins are not supported by the remote API.' => 'client_login_failed',
        'The SessionID is empty.' => 'session_id_empty',
        'Wrong SessionID datatype.' => 'session_id_nostring',
        'The Session failure limit has been reached.' => 'session_failure_limit',
        'The Session is expired or does not exist.' => 'session_does_not_exist',
        'Session IP mismatch.' => 'session_ip_mismatch',
        'You do not have the permissions to access this function.' => 'permission_denied',
        'Invalid function parameter.' => 'invalid_function_parameter',
        'The ID has to be > 0.' => 'invalid_id',
        'This ISPConfig installation is currently under maintenance. We should be back shortly. Thank you for your patience.' => 'maintenance_mode',
        'Invalid domain name.' => 'no_domain_found',
        'There is no domain ID with informed domain name.' => 'no_domain_found',
        'The status is not available' => 'status_undefined',
        'Invalid backup_id' => 'invalid_backup_id',
    ];

    /**
     * Fault codes that mean the login itself was refused.
     */
    public const array LOGIN_FAULTS = [
        'login_failed', 'login_failure_limit', 'login_username_empty', 'login_password_empty',
        'client_login_failed', 'session_failure_limit', 'session_ip_mismatch',
    ];

    /**
     * @param array<array-key, mixed> $payload
     */
    final public function __construct(
        string $message,
        private readonly string $function,
        private readonly string $errorCode,
        private readonly ?string $faultCode = null,
        private readonly ?int $httpStatus = null,
        private readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromResponse(string $function, string $code, string $message, int $httpStatus, array $payload): static
    {
        $fault = $code === 'remote_fault' ? self::faultFromMessage($message) : $code;
        $text = $message !== '' ? trim($message) : $code;

        return new static(
            sprintf('ISPConfig %s failed: %s%s', $function, $text, $fault !== null && $fault !== $code ? ' [' . $fault . ']' : ''),
            $function,
            $code,
            $fault,
            $httpStatus,
            $payload,
        );
    }

    public static function transport(string $function, Throwable $previous): static
    {
        return new static(
            sprintf('Could not connect to ISPConfig for %s: %s', $function, $previous->getMessage()),
            $function,
            'connection_error',
            previous: $previous,
        );
    }

    public static function unexpectedResponse(string $function, int $httpStatus): static
    {
        return new static(
            sprintf('ISPConfig %s returned an unexpected response (HTTP %d).', $function, $httpStatus),
            $function,
            'unexpected_response',
            httpStatus: $httpStatus,
        );
    }

    /**
     * Restores the SOAP fault code of a known fault string, "data_processing_error" for validation messages.
     */
    public static function faultFromMessage(string $message): ?string
    {
        $message = trim($message);

        if (isset(self::FAULTS[$message])) {
            return self::FAULTS[$message];
        }

        return match (true) {
            str_starts_with($message, 'Invalid value for the field'),
            str_starts_with($message, 'Invalid domain name in the field') => 'data_processing_error',
            str_starts_with($message, 'Invalid or non existant backup_id') => 'invalid_backup_id',
            str_starts_with($message, 'Invalid action_type') => 'invalid_action',
            str_starts_with($message, 'There is already a pending') => 'duplicate_action',
            default => null,
        };
    }

    /**
     * Remote function that failed, e.g. "sites_web_domain_add".
     */
    public function function(): string
    {
        return $this->function;
    }

    /**
     * The "code" of the JSON answer: "remote_fault", "invalid_method", "invalid_data", ...
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * SOAP fault code of the error when it is known, e.g. "permission_denied" or "session_does_not_exist".
     */
    public function faultCode(): ?string
    {
        return $this->faultCode;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function isPermissionDenied(): bool
    {
        return $this->faultCode === 'permission_denied';
    }

    public function isSessionExpired(): bool
    {
        return $this->faultCode === 'session_does_not_exist';
    }

    public function isValidationError(): bool
    {
        return $this->faultCode === 'data_processing_error';
    }
}
