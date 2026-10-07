<?php

declare(strict_types=1);

namespace App\Security\Native;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/** Google could not be asked whether a token is genuine: the account is not at fault (#1133). */
final class GoogleUnavailableException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'google_unavailable';
    }
}
