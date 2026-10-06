<?php

declare(strict_types=1);

namespace App\Tests\Integration\Procedure\Moderation;

use App\Entity\RefreshToken;
use App\Enum\Moderation\ModerationActionType;
use App\Repository\Moderation\ModerationActionRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\User\DeviceTokenRepository;
use App\Service\Procedure\Moderation\AccountSuspensionProcedure;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

        $this->procedure()->suspend($account, 'Spam répété', $moderator);

        $entityManager->clear();
        $this->assertSame(0, $entityManager->getRepository(RefreshToken::class)->count(['username' => 'spammer']));
        $this->assertSame(0, self::getContainer()->get(DeviceTokenRepository::class)->count());
        $actions = self::getContainer()->get(ModerationActionRepository::class)->findAll();
        $this->assertCount(1, $actions);
        $this->assertSame(ModerationActionType::AccountSuspended, $actions[0]->type);
        $this->assertSame('Spam répété', $actions[0]->reason);
        $this->assertSame($moderator->id, $actions[0]->moderator->id);
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
    }

    private function procedure(): AccountSuspensionProcedure
    {
        return new AccountSuspensionProcedure(
            self::getContainer()->get(EntityManagerInterface::class),
            new RefreshTokenRepository(self::getContainer()->get(ManagerRegistry::class)),
            self::getContainer()->get(DeviceTokenRepository::class),
        );
    }
}
