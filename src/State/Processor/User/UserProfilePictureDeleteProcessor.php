<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Image\UserProfilePicture;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserProfilePicture, null>
 */
readonly class UserProfilePictureDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var UserProfilePicture $data */
        $user = $data->getUser();
        assert($user !== null);

        // Detach profile picture from user
        $user->setProfilePicture(null);
        $this->entityManager->flush();

        // Remove the profile picture entity (VichUploader will handle file deletion)
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
