<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses a suspended account (#1116). On the api firewall it also refuses a JWT issued before the
 * suspension, which would otherwise work until it expires. Checked after authentication, so only
 * someone holding the password or a valid token learns that the account is suspended.
 */
class SuspensionChecker implements UserCheckerInterface
{
    public const string MESSAGE_KEY = 'account_suspended';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && $user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException(self::MESSAGE_KEY);
        }
    }
}
