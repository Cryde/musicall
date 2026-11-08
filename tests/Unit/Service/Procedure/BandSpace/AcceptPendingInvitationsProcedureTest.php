<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Procedure\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\BandSpaceChangeSignal;
use App\Service\Procedure\BandSpace\AcceptPendingInvitationsProcedure;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** Both sign-up paths call it after the account is committed, so a failure here must not fail them. */
class AcceptPendingInvitationsProcedureTest extends TestCase
{
    public function test_a_failure_is_logged_drops_its_signal_and_never_breaks_the_sign_up(): void
    {
        $failure = new \RuntimeException('The database went away');
        $invitationRepository = $this->createStub(BandSpaceInvitationRepository::class);
        $invitationRepository->method('findPendingByEmail')->willThrowException($failure);
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        // Nothing joined, so the band must not be told to refetch its members.
        $changeSignal = $this->createMock(BandSpaceChangeSignal::class);
        $changeSignal->expects($this->once())->method('reset');
        $user = new User();
        $user->id = 'd1be73fc-b0c4-4530-a30a-d41f43e6ebea';
        $user->email = 'nouveau@example.com';

        $procedure = new AcceptPendingInvitationsProcedure(
            $invitationRepository,
            $this->createStub(BandSpaceMembershipRepository::class),
            $this->createStub(MessageThreadMetaRepository::class),
            $this->createStub(BandSpaceActivityRecorder::class),
            $changeSignal,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $logger,
        );
        $procedure->process($user);

        $this->assertSame([[
            'error',
            'Failed to accept the pending band space invitations of a new account',
            ['user_id' => 'd1be73fc-b0c4-4530-a30a-d41f43e6ebea', 'exception' => $failure],
        ]], $logger->records);
    }
}
