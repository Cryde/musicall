<?php

declare(strict_types=1);

namespace App\State\Processor\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Profile\UserProfileCoverPictureUpload;
use App\Entity\Image\UserProfileCoverPicture;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<UserProfileCoverPictureUpload, UserProfileCoverPictureUpload>
 */
readonly class UserProfileCoverPictureProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserProfileCoverPictureUpload
    {
        /** @var UserProfileCoverPictureUpload $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getProfile();
        assert($profile !== null);

        $previousCoverPicture = $profile->getCoverPicture();
        if ($previousCoverPicture !== null) {
            $profile->setCoverPicture(null);
            $this->entityManager->flush();
            $this->entityManager->remove($previousCoverPicture);
            $this->entityManager->flush();
        }

        // Create new cover picture entity from the uploaded data
        $coverPicture = new UserProfileCoverPicture();
        $coverPicture->setImageFile($data->imageFile);
        $coverPicture->setProfile($profile);

        $profile->setCoverPicture($coverPicture);
        $this->entityManager->persist($coverPicture);
        $this->entityManager->flush();

        return $data;
    }
}
