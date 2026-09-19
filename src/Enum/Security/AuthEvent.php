<?php

declare(strict_types=1);

namespace App\Enum\Security;

/**
 * The auth lifecycle events on the `auth` log channel (#1021). Successes are logged too: without
 * the denominator there is no failure rate, only a counter that goes up.
 */
enum AuthEvent: string
{
    case LoginSuccess = 'auth.login.success';
    case RefreshSuccess = 'auth.refresh.success';
    case RefreshFailure = 'auth.refresh.failure';
    case RefreshNotFound = 'auth.refresh.not_found';
    case JwtRejected = 'auth.jwt.rejected';
    case Logout = 'auth.logout';

    public function outcome(): string
    {
        return match ($this) {
            self::LoginSuccess, self::RefreshSuccess, self::Logout => 'success',
            self::RefreshFailure, self::RefreshNotFound, self::JwtRejected => 'failure',
        };
    }
}
