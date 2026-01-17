<?php

declare(strict_types=1);

namespace App\State\Processor\User\NotificationPreference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\NotificationPreference\UserNotificationPreferenceEdit;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
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
        $preference = $user->getNotificationPreference();

        if (!$preference) {
            $preference = new UserNotificationPreference();
            $user->setNotificationPreference($preference);
            $this->entityManager->persist($preference);
        }

        $preference->setSiteNews($data->siteNews);
        $preference->setWeeklyRecap($data->weeklyRecap);
        $preference->setMessageReceived($data->messageReceived);
        $preference->setPublicationComment($data->publicationComment);
        $preference->setForumReply($data->forumReply);
        $preference->setMarketing($data->marketing);
        $preference->setActivityReminder($data->activityReminder);
        $preference->setUpdateDatetime(new DateTimeImmutable());

        $this->entityManager->flush();

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
