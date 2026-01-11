<?php

declare(strict_types=1);

namespace App\State\Processor\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Profile\UserProfileEdit;
use App\Entity\User;
use App\State\Provider\User\Profile\UserProfileEditProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<UserProfileEdit, UserProfileEdit>
 */
readonly class UserProfileEditProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserProfileEditProvider $userProfileEditProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserProfileEdit
    {
        /** @var UserProfileEdit $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getProfile();

        $profile->setDisplayName($data->displayName);
        $profile->setBio($data->bio);
        $profile->setLocation($data->location);
        $profile->setIsPublic($data->isPublic);
        $profile->setUpdateDatetime(new DateTimeImmutable());

        $this->entityManager->flush();

        return $this->userProfileEditProvider->provide($operation, $uriVariables, $context);
    }
}
