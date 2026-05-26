<?php declare(strict_types=1);

namespace App\Service\Builder\Notification;

use App\ApiResource\Notification\UserNotification;
use App\Entity\Notification\Notification;

readonly class NotificationBuilder
{
    public function buildFromEntity(Notification $notification): UserNotification
    {
        $dto = new UserNotification();
        $dto->id = (string) $notification->id;
        $dto->type = $notification->type->value;
        $dto->payload = $notification->payload;
        $dto->readDatetime = $notification->readDatetime?->format(DATE_ATOM);
        $dto->creationDatetime = $notification->creationDatetime->format(DATE_ATOM);

        return $dto;
    }

    /**
     * @param Notification[] $notifications
     * @return UserNotification[]
     */
    public function buildFromList(array $notifications): array
    {
        return array_map(fn (Notification $notification): UserNotification => $this->buildFromEntity($notification), $notifications);
    }
}
