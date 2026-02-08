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
        $oldUsername = $user->getUsername();
        $uuid = $user->getId();

        // Anonymize user data
        $user->setUsername('deleted_' . $uuid);
        $user->setEmail('deleted_' . $uuid . '@deleted.local');
        $user->setPassword(null);
        $user->setRoles([]);
        $user->setToken(null);
        $user->setResetRequestDatetime(null);
        $user->setConfirmationDatetime(null);
        $user->setLastLoginDatetime(null);
        $user->setUsernameChangedDatetime(null);
        $user->setDeletionDatetime(new \DateTimeImmutable());

        // Remove profile picture (cascade removes entity + VichUploader removes file)
        $user->setProfilePicture(null);

        // Clear social accounts (orphanRemoval)
        $user->getSocialAccounts()->clear();

        // Remove notification preference
        $user->setNotificationPreference(null);

        // Remove musician profile
        $user->setMusicianProfile(null);

        // Remove teacher profile
        $user->setTeacherProfile(null);

        // Anonymize user profile
        $profile = $user->getProfile();
        $profile->setBio(null);
        $profile->setDisplayName(null);
        $profile->setLocation(null);
        $profile->setIsPublic(false);
        $profile->setCoverPicture(null);
        $profile->getSocialLinks()->clear();

        // Delete refresh tokens (stored by username string, not FK)
        $this->connection->executeStatement(
            'DELETE FROM refresh_tokens WHERE username = :oldUsername',
            ['oldUsername' => $oldUsername]
        );

        $this->entityManager->flush();
    }
}
