<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Updates User::lastActivityDatetime on every authenticated main request,
 * write-throttled so we don't issue a DB write per request.
 *
 * Consumed by MessageSenderProcedure to skip the message-received email
 * when the recipient is presently active in the app (#712). Designed to
 * be a reusable presence signal for any future feature that needs "user
 * is active right now."
 *
 * Throttle: WRITE_INTERVAL_SECONDS = 60. The consuming "active recipient"
 * window is 5 minutes (in MessageSenderProcedure), comfortably wider than
 * the write throttle so we never misclassify an active user as idle.
 *
 * Runs on every main request; sub-requests and unauthenticated requests
 * are skipped early.
 */
#[AsEventListener(event: KernelEvents::REQUEST)]
readonly class UserActivityListener
{
    private const int WRITE_INTERVAL_SECONDS = 60;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $now = new DateTimeImmutable();
        if ($user->lastActivityDatetime !== null
            && $now->getTimestamp() - $user->lastActivityDatetime->getTimestamp() < self::WRITE_INTERVAL_SECONDS
        ) {
            return;
        }

        $user->lastActivityDatetime = $now;
        $this->entityManager->flush();
    }
}
