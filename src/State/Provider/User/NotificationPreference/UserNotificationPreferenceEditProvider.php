<?php

declare(strict_types=1);

namespace App\State\Provider\User\NotificationPreference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\NotificationPreference\UserNotificationPreferenceEdit;
use App\Entity\User;
use App\Service\Builder\User\NotificationPreference\UserNotificationPreferenceMapper;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<UserNotificationPreferenceEdit>
 */
readonly class UserNotificationPreferenceEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserNotificationPreferenceEdit
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        $preference = $user?->notificationPreference;

        if (!$preference) {
            return new UserNotificationPreferenceEdit();
        }

        return UserNotificationPreferenceMapper::toResource($preference);
    }
}
