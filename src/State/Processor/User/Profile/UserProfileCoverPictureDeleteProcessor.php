<?php

declare(strict_types=1);

namespace App\State\Processor\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Image\UserProfileCoverPicture;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserProfileCoverPicture, null>
 */
readonly class UserProfileCoverPictureDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var UserProfileCoverPicture $data */
        $profile = $data->getProfile();

        // Detach cover picture from profile
        $profile->setCoverPicture(null);
        $this->entityManager->flush();

        // Remove the cover picture entity (VichUploader will handle file deletion)
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
