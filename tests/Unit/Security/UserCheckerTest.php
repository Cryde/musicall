<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Exception\NotConfirmedAccountException;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    public function test_check_pre_auth()
    {
        $checker = new UserChecker();

        // this non-internal user as a confirmationDatetime to null
        // if it was an internal user it would throw
        $checker->checkPreAuth($this->buildNonInternalUser());

        $user = new User();
        $user->setConfirmationDatetime(new \DateTime());
        $checker->checkPreAuth($user);

        $user->setConfirmationDatetime(null);
        $this->expectException(NotConfirmedAccountException::class);
        $this->expectExceptionMessage('Vous devez confirmer votre compte pour pouvoir vous connecter');
        $checker->checkPreAuth($user);
    }

    public function test_check_post_auth()
    {
        // i don't know if this test is useful
        $checker = new UserChecker();

        $checker->checkPostAuth($this->buildNonInternalUser());

        $user = new User();
        $checker->checkPostAuth($user);

        $this->assertTrue(true); //
    }

    private function buildNonInternalUser(): UserInterface
    {
        // this user is ok with UserInterface but is not one of our "User" implementation
        return new class() implements UserInterface {
            private ?\DateTimeInterface $confirmationDatetime = null;

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials()
            {
            }

            public function getUserIdentifier(): string
            {
                return '123';
            }

            public function getConfirmationDatetime(): ?\DateTimeInterface
            {
                return $this->confirmationDatetime;
            }
        };
    }
}