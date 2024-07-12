<?php

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserProfilePictureProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param UserProfilePicture $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $previousProfilePicture = $user->getProfilePicture() ?: null;
        if ($previousProfilePicture) {
            $user->setProfilePicture(null);
            $this->entityManager->flush();
            $this->entityManager->remove($previousProfilePicture);
            $this->entityManager->flush();
        }

        $data->setUser($user);
        $user->setProfilePicture($data);
        $this->entityManager->flush();

        return $data;
    }
}