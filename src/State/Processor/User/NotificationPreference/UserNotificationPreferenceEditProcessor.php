<?php

declare(strict_types=1);

namespace App\State\Processor\User\NotificationPreference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\NotificationPreference\UserNotificationPreferenceEdit;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Service\Builder\User\NotificationPreference\UserNotificationPreferenceMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<UserNotificationPreferenceEdit, UserNotificationPreferenceEdit>
 */
readonly class UserNotificationPreferenceEditProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserNotificationPreferenceEdit
    {
        /** @var UserNotificationPreferenceEdit $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $preference = $user->notificationPreference;

        if (!$preference) {
            $preference = new UserNotificationPreference();
            $preference->user = $user;
            $user->notificationPreference = $preference;
            $this->entityManager->persist($preference);
        }

        UserNotificationPreferenceMapper::apply($data, $preference);
        $preference->updateDatetime = new DateTimeImmutable();

        $this->entityManager->flush();

        return UserNotificationPreferenceMapper::toResource($preference);
    }
}
