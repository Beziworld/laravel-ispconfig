<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Exceptions;

/**
 * Raised when ISPConfig refuses the login of the remote user.
 *
 * A wrong password, an unknown user and a refused client address all give
 * "login_failed". Logins are never retried: more than 10 failed attempts
 * within 5 minutes block the address with "login_failure_limit".
 */
final class AuthenticationException extends ApiException {}
