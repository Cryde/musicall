<?php

declare(strict_types=1);

namespace App\Tests\Factory\Notification;

use App\Entity\Notification\Notification;
use App\Enum\Notification\NotificationType;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Notification>
 */
final class NotificationFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'recipient' => UserFactory::new(),
            'type' => NotificationType::ForumTopicReply,
            'payload' => [],
            'creationDatetime' => DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    public function read(?DateTimeImmutable $at = null): static
    {
        return $this->with(['readDatetime' => $at ?? new DateTimeImmutable()]);
    }

    public static function class(): string
    {
        return Notification::class;
    }
}
