<?php

declare(strict_types=1);

namespace App\State\Provider\User\NotificationPreference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\NotificationPreference\UserNotificationPreferenceEdit;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
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
        $preference = $user?->getNotificationPreference();

        if (!$preference) {
            return new UserNotificationPreferenceEdit();
        }

        return $this->buildFromEntity($preference);
    }

    private function buildFromEntity(UserNotificationPreference $preference): UserNotificationPreferenceEdit
    {
        $dto = new UserNotificationPreferenceEdit();

        $dto->siteNews = $preference->isSiteNews();
        $dto->weeklyRecap = $preference->isWeeklyRecap();
        $dto->messageReceived = $preference->isMessageReceived();
        $dto->publicationComment = $preference->isPublicationComment();
        $dto->forumReply = $preference->isForumReply();
        $dto->marketing = $preference->isMarketing();
        $dto->activityReminder = $preference->isActivityReminder();

        return $dto;
    }
}
