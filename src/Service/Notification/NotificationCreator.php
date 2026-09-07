<?php declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Mercure\MercureTopic;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Persists notifications. Producers (event listeners) build the recipient
 * list and call this; the contract (see epic #689) is that creation is a
 * best-effort side-effect dispatched after the triggering action commits.
 */
readonly class NotificationCreator
{
    /**
     * A tag, not the notification. The browser calls the count endpoint it already calls, so there is
     * no second payload shape to keep in step with the API, no ordering to guarantee, and nothing
     * arriving over SSE that ends up rendered. See decision 4 of epic #948.
     */
    private const string SIGNAL = '{"type":"notification"}';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
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

        $this->signal([$recipient->id]);
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
            $recipientId = $recipient->id;
            if ($recipientId === '' || isset($seen[$recipientId])) {
                continue;
            }
            // Keyed to de-duplicate, valued so array_values() below returns a list<string> without
            // anything having to assert that array keys are still strings.
            $seen[$recipientId] = $recipientId;
            $this->entityManager->persist($this->build($recipient, $type, $payload));
        }

        if ($seen === []) {
            return;
        }

        $this->entityManager->flush();

        // The de-duplicated set, so a recipient named twice is still signalled once.
        $this->signal(array_values($seen));
    }

    /**
     * Tells the recipients that something changed, after their notifications are already persisted.
     *
     * **One update carrying every topic, not one publish per recipient.** A publish is a blocking
     * HTTP round trip, so per-recipient fan-out would multiply any hub slowness by the size of the
     * recipient list, and some lists are unbounded: a forum reply notifies every participant of the
     * topic. The hub delivers a private update to a subscriber when one of its topics is both asked
     * for and authorized by their token, so N topics reach N recipients and nobody else, and the
     * frame carries only the id and the data, so a recipient never learns who else was on the list.
     * Verified against the hub rather than assumed.
     *
     * **Private**, which is the whole of the authorization: the hub consults a subscriber's topic
     * selectors only for private updates, so a public one here would hand every connected browser
     * every user's signal.
     *
     * **Never throws.** The notifications exist and are flushed by the time this runs, so an
     * unreachable hub costs the live update and nothing else; the browser's fallback poll picks it
     * up within a few minutes, or on its next tab focus. Creation must not fail for a side channel,
     * which is the same line the epic #689 listener contract holds.
     *
     * @param list<string> $recipientIds
     */
    private function signal(array $recipientIds): void
    {
        $topics = array_map(MercureTopic::userNotifications(...), $recipientIds);

        try {
            $this->hub->publish(new Update($topics, self::SIGNAL, private: true));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not publish a notification signal, the bell will fall back to polling', [
                'exception' => $throwable,
                'recipient_ids' => $recipientIds,
            ]);
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
