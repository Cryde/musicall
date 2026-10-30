<?php

declare(strict_types=1);

namespace App\Security\Native;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/** Apple could not be asked whether a token is genuine: the account is not at fault (#1269). */
final class AppleUnavailableException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'apple_unavailable';
    }
}
