<?php

declare(strict_types=1);

namespace App\Service\Procedure\User;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

readonly class DeleteAccountProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    public function process(User $user): void
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

        // Delete refresh tokens (stored by username string, not FK)
        $this->connection->executeStatement(
            'DELETE FROM refresh_tokens WHERE username = :oldUsername',
            ['oldUsername' => $oldUsername]
        );

        $this->entityManager->flush();
    }
}
