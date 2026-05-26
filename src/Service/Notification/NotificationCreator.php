<?php declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists notifications. Producers (event listeners) build the recipient
 * list and call this; the contract (see epic #689) is that creation is a
 * best-effort side-effect dispatched after the triggering action commits.
 */
readonly class NotificationCreator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Persists a single notification and flushes immediately. Like
     * {@see createForRecipients()}, this is a best-effort side-effect and must
     * be called only after the triggering action's transaction has committed
     * (epic #689 contract): it flushes the EntityManager, so calling it from a
     * preFlush/onFlush listener would be unsafe. The caller excludes the actor.
     *
     * @param array<string, mixed> $payload
     */
    public function create(User $recipient, NotificationType $type, array $payload): void
    {
        $this->entityManager->persist($this->build($recipient, $type, $payload));
        $this->entityManager->flush();
    }

    /**
     * One notification per distinct recipient, single flush. Skips nulls and
     * de-duplicates by user id (callers are responsible for excluding the actor).
     *
     * @param iterable<User|null> $recipients
     * @param array<string, mixed> $payload
     */
    public function createForRecipients(iterable $recipients, NotificationType $type, array $payload): void
    {
        $seen = [];
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }
            $recipientId = (string) $recipient->id;
            if ($recipientId === '' || isset($seen[$recipientId])) {
                continue;
            }
            $seen[$recipientId] = true;
            $this->entityManager->persist($this->build($recipient, $type, $payload));
        }

        if ($seen !== []) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function build(User $recipient, NotificationType $type, array $payload): Notification
    {
        $notification = new Notification();
        $notification->recipient = $recipient;
        $notification->type = $type;
        $notification->payload = $payload;

        return $notification;
    }
}
