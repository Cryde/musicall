<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    public function test_check_pre_auth_is_a_noop(): void
    {
        // The verification check moved to checkPostAuth (so account_not_verified is
        // only disclosed after the password is verified), leaving checkPreAuth a
        // no-op for every user - including an unverified one.
        $checker = new UserChecker();

        $checker->checkPreAuth($this->buildNonInternalUser());

        $verifiedUser = new User();
        $verifiedUser->confirmationDatetime = new \DateTime();
        $checker->checkPreAuth($verifiedUser);

        $unverifiedUser = new User();
        $unverifiedUser->confirmationDatetime = null;
        $unverifiedUser->email = 'test@example.com';
        $checker->checkPreAuth($unverifiedUser);

        $this->expectNotToPerformAssertions();
    }

    public function test_check_post_auth_throws_for_unverified_user(): void
    {
        $checker = new UserChecker();

        // non-App user: ignored
        $checker->checkPostAuth($this->buildNonInternalUser());

        // verified user: no exception
        $verifiedUser = new User();
        $verifiedUser->confirmationDatetime = new \DateTime();
        $checker->checkPostAuth($verifiedUser);

        // unverified user: only now (after the password check) is the status disclosed
        $unverifiedUser = new User();
        $unverifiedUser->confirmationDatetime = null;
        $unverifiedUser->email = 'test@example.com';
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('account_not_verified');
        $checker->checkPostAuth($unverifiedUser);
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

            public function eraseCredentials(): void
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
