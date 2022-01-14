<?php

namespace App\Security;

use App\Entity\User;
use App\Exception\NotConfirmedAccountException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * @return void
     * @throws NotConfirmedAccountException
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getConfirmationDatetime()) {
            throw new NotConfirmedAccountException('Vous devez confirmer votre compte pour pouvoir vous connecter');
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }
    }
}