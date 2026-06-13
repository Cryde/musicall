<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    /**
     * Checked AFTER credential verification (not in checkPreAuth) so the
     * "account not verified" status - and the email it carries - is only ever
     * disclosed to someone who supplied the correct password, i.e. the account
     * owner. A wrong password yields a generic "invalid credentials" error
     * instead, which closes the user-enumeration / email-disclosure oracle.
     *
     * @throws CustomUserMessageAccountStatusException
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->confirmationDatetime instanceof \DateTimeInterface) {
            throw new CustomUserMessageAccountStatusException('account_not_verified', ['{{ email }}' => $user->email]);
        }
    }
}
