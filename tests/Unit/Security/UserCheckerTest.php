<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\SuspensionChecker;
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
        $checker = new UserChecker(new SuspensionChecker());

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
        $checker = new UserChecker(new SuspensionChecker());

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

    /** Suspension is checked before the confirmation, so a suspended account never learns more. */
    public function test_check_post_auth_refuses_a_suspended_user(): void
    {
        $user = new User();
        $user->confirmationDatetime = new \DateTime('2026-01-01');
        $user->suspensionDatetime = new \DateTimeImmutable('2026-09-01');

        try {
            (new UserChecker(new SuspensionChecker()))->checkPostAuth($user);
            $this->fail('A suspended user must be refused');
        } catch (CustomUserMessageAccountStatusException $exception) {
            $this->assertSame('account_suspended', $exception->getMessageKey());
        }
    }
}
