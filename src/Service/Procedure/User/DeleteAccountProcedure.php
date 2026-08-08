<?php

declare(strict_types=1);

namespace App\Service\Procedure\User;

use App\Entity\User;
use App\Event\BandSpaceMemberRoleChangedEvent;
use App\Service\Procedure\BandSpace\WithdrawUserFromBandSpacesProcedure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class DeleteAccountProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private WithdrawUserFromBandSpacesProcedure $withdrawUserFromBandSpacesProcedure,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(User $user): void
    {
        // One transaction, because not every step below waits for the flush: the band space
        // withdrawal deletes future planned finance entries with a bulk DQL query and the refresh
        // tokens go out through the connection, so both reach the database straight away. Without
        // this, a flush that failed would leave a member's finance rows deleted for good while the
        // account they belong to is still standing.
        $promotions = $this->entityManager->wrapInTransaction(
            fn(): array => $this->anonymize($user),
        );

        // Best-effort notifications dispatched after the commit (epic #689 contract).
        foreach ($promotions as $promotion) {
            $this->eventDispatcher->dispatch($promotion);
        }
    }

    /**
     * @return list<BandSpaceMemberRoleChangedEvent> the promotions to announce once committed
     */
    private function anonymize(User $user): array
    {
        $oldUsername = $user->username;
        $uuid = $user->id;

        // Anonymize user data
        $user->username = 'deleted_' . $uuid;
        $user->email = 'deleted_' . $uuid . '@deleted.local';
        $user->password = null;
        $user->roles = [];
        $user->token = null;
        $user->resetRequestDatetime = null;
        $user->confirmationDatetime = null;
        $user->lastLoginDatetime = null;
        $user->usernameChangedDatetime = null;
        $user->deletionDatetime = new \DateTimeImmutable();

        // Remove profile picture (cascade removes entity + VichUploader removes file)
        $user->profilePicture = null;

        // Clear social accounts (orphanRemoval)
        $user->socialAccounts->clear();

        // Remove notification preference
        $user->notificationPreference = null;

        // Remove musician profile
        $user->musicianProfile = null;

        // Remove teacher profile
        $user->teacherProfile = null;

        // Anonymize user profile
        $profile = $user->profile;
        $profile->bio = null;
        $profile->displayName = null;
        $profile->location = null;
        $profile->isPublic = false;
        $profile->coverPicture = null;
        $profile->socialLinks->clear();

        // Take the account out of its band spaces. Runs after the anonymization so the memberships it
        // closes and the notification it triggers carry the anonymized handle, not the old username.
        $promotions = $this->withdrawUserFromBandSpacesProcedure->process($user);

        // Delete refresh tokens (stored by username string, not FK)
        $this->connection->executeStatement(
            'DELETE FROM refresh_tokens WHERE username = :oldUsername',
            ['oldUsername' => $oldUsername]
        );

        // wrapInTransaction() flushes before it commits, so the caller gets one atomic unit.
        return $promotions;
    }
}
