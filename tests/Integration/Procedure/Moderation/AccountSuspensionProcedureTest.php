<?php

declare(strict_types=1);

namespace App\Tests\Integration\Procedure\Moderation;

use App\Entity\RefreshToken;
use App\Enum\Moderation\ModerationActionType;
use App\Enum\Moderation\ModerationBasis;
use App\Enum\User\UserEmailType;
use App\Repository\Moderation\ModerationActionRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\User\UserEmailLogRepository;
use App\Repository\User\DeviceTokenRepository;
use App\Service\Mail\Brevo\Moderation\SuspensionLiftedEmail;
use App\Service\Procedure\Moderation\AccountSuspensionProcedure;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AccountSuspensionProcedureTest extends KernelTestCase
{
    public function test_suspending_ends_sessions_and_push_and_is_recorded(): void
    {
        $moderator = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        DeviceTokenFactory::new(['user' => $account])->create();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $refreshToken = RefreshToken::createForUserWithTtl('a-refresh-token', $account, 3600);
        $entityManager->persist($refreshToken);
        $entityManager->flush();

        $this->procedure()->suspend($account, 'Spam répété', ModerationBasis::Spam, $moderator);

        $entityManager->clear();
        $this->assertSame(0, $entityManager->getRepository(RefreshToken::class)->count(['username' => 'spammer']));
        $this->assertSame(0, self::getContainer()->get(DeviceTokenRepository::class)->count());
        $actions = self::getContainer()->get(ModerationActionRepository::class)->findAll();
        $this->assertCount(1, $actions);
        $this->assertSame(ModerationActionType::AccountSuspended, $actions[0]->type);
        $this->assertSame('Spam répété', $actions[0]->reason);
        $this->assertSame($moderator->id, $actions[0]->moderator->id);
        $this->assertSame(ModerationBasis::Spam, $actions[0]->basis);
        // The statement of reasons (#1126), its params pinned by AdminReportTest.
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', 'spammer@test.com');
        $logs = self::getContainer()->get(UserEmailLogRepository::class)->findAll();
        $this->assertCount(1, $logs);
        $this->assertSame(UserEmailType::ACCOUNT_SUSPENDED_STATEMENT, $logs[0]->emailType);
        $this->assertSame($actions[0]->id, $logs[0]->referenceId);
    }

    public function test_a_closed_account_is_suspended_without_an_email(): void
    {
        $moderator = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create(['username' => 'ferme', 'email' => 'ferme@test.com', 'deletionDatetime' => new \DateTimeImmutable()]);

        $this->procedure()->suspend($account, 'Spam répété', ModerationBasis::Spam, $moderator);

        $this->assertTrue($account->isSuspended());
        $this->assertEmailCount(0);
    }

    public function test_lifting_restores_the_account_and_is_recorded(): void
    {
        $moderator = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create([
            'username' => 'spammer',
            'email' => 'spammer@test.com',
            'suspensionDatetime' => new \DateTimeImmutable(),
            'suspensionReason' => 'Spam répété',
        ]);

        $this->procedure()->lift($account, $moderator);

        $this->assertFalse($account->isSuspended());
        $this->assertNull($account->suspensionReason);
        $actions = self::getContainer()->get(ModerationActionRepository::class)->findAll();
        $this->assertSame(ModerationActionType::SuspensionLifted, $actions[0]->type);
        $this->assertNull($actions[0]->basis);
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', 'spammer@test.com');
        $logs = self::getContainer()->get(UserEmailLogRepository::class)->findAll();
        $this->assertCount(1, $logs);
        $this->assertSame(UserEmailType::SUSPENSION_LIFTED, $logs[0]->emailType);
    }

    public function test_a_closed_account_is_lifted_without_an_email(): void
    {
        $moderator = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create([
            'username' => 'ferme',
            'email' => 'ferme@test.com',
            'suspensionDatetime' => new \DateTimeImmutable(),
            'suspensionReason' => 'Spam répété',
            'deletionDatetime' => new \DateTimeImmutable(),
        ]);

        $this->procedure()->lift($account, $moderator);

        $this->assertFalse($account->isSuspended());
        $this->assertEmailCount(0);
    }

    /** The email is sent after the commit: a failure is logged and never undoes the lift (#1126). */
    public function test_an_email_failure_does_not_undo_the_lift(): void
    {
        $moderator = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create([
            'username' => 'spammer',
            'email' => 'spammer@test.com',
            'suspensionDatetime' => new \DateTimeImmutable(),
            'suspensionReason' => 'Spam répété',
        ]);
        self::getContainer()->set(SuspensionLiftedEmail::class, new class extends SuspensionLiftedEmail {
            public function __construct()
            {
            }

            public function send(string $recipientEmail, string $username, \DateTimeImmutable $liftedAt, string $locale): void
            {
                throw new \RuntimeException('Brevo is down');
            }
        });

        $this->procedure()->lift($account, $moderator);

        $this->assertFalse($account->isSuspended());
        $actions = self::getContainer()->get(ModerationActionRepository::class)->findAll();
        $this->assertSame(ModerationActionType::SuspensionLifted, $actions[0]->type);
        // Nothing was sent, so the admin page must not claim it was.
        $this->assertSame(0, self::getContainer()->get(UserEmailLogRepository::class)->count());
    }

    private function procedure(): AccountSuspensionProcedure
    {
        return new AccountSuspensionProcedure(
            self::getContainer()->get(EntityManagerInterface::class),
            new RefreshTokenRepository(self::getContainer()->get(ManagerRegistry::class)),
            self::getContainer()->get(DeviceTokenRepository::class),
            self::getContainer()->get(EventDispatcherInterface::class),
        );
    }
}
