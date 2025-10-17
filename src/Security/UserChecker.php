<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Exception\NotConfirmedAccountException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * @throws NotConfirmedAccountException
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getConfirmationDatetime()) {
            throw new NotConfirmedAccountException('Vous devez confirmer votre compte pour pouvoir vous connecter');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
    }
}
