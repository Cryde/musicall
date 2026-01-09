<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use App\Service\File\RemoteFileDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class ProfilePictureImporter
{
    public function __construct(
        private RemoteFileDownloader $remoteFileDownloader,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger,
    ) {
    }

    public function importFromUrl(User $user, string $pictureUrl): void
    {
        // Skip if user already has a profile picture
        if ($user->getProfilePicture() !== null) {
            return;
        }

        try {
            $destination = $this->parameterBag->get('file_user_profile_picture_destination');
            [$filename, $fileSize] = $this->remoteFileDownloader->download($pictureUrl, $destination);

            $profilePicture = new UserProfilePicture();
            $profilePicture->setImageName($filename);
            $profilePicture->setImageSize($fileSize);
            $profilePicture->setUpdatedAt(new \DateTime());
            $profilePicture->setUser($user);

            $user->setProfilePicture($profilePicture);

            $this->entityManager->persist($profilePicture);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to import profile picture from OAuth provider', [
                'userId' => $user->getId(),
                'pictureUrl' => $pictureUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
