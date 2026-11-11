<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\User\ClientPlatform;
use App\Http\ClientHeaders;
use App\Repository\User\UserDailyActivityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Stamps a signed-in member's activity on their main requests, at most once a minute: the presence
 * signal MessageSenderProcedure reads (#712), and the platform and app version they are on, with one
 * UserDailyActivity row per day and platform (#1370).
 */
#[AsEventListener(event: KernelEvents::REQUEST)]
readonly class UserActivityListener
{
    private const int WRITE_INTERVAL_SECONDS = 60;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserDailyActivityRepository $userDailyActivityRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
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

        $now = $this->clock->now();
        if ($this->isThrottled($user->lastActivityDatetime, $now)) {
            return;
        }

        [$platform, $appVersion] = $this->platformAndVersionOf($user, ClientHeaders::of($event->getRequest()));

        // Before the flush, in its own statement, so the foreign key lock on the member's row is released
        // before the UPDATE needs it. A failure is retried by the next write; the presence signal still goes out.
        try {
            $this->userDailyActivityRepository->record($user, $now, $platform, $appVersion);
        } catch (DBALException $exception) {
            $this->logger->error('Could not record the daily activity of user {userId}', ['userId' => $user->id, 'exception' => $exception]);
        }

        $user->lastActivityDatetime = $now;
        $user->lastPlatform = $platform;
        $user->lastAppVersion = $appVersion;
        $this->entityManager->flush();
    }

    /**
     * The app's image requests carry its token but not its headers, so they look like a build older than
     * the headers: a member already seen on an app is taken to still be on it.
     *
     * @return array{ClientPlatform, ?string}
     */
    private function platformAndVersionOf(User $user, ClientHeaders $client): array
    {
        $platform = $client->signedInPlatform();
        if ($platform === ClientPlatform::UnknownApp
            && ($user->lastPlatform === ClientPlatform::Ios || $user->lastPlatform === ClientPlatform::Android)
        ) {
            return [$user->lastPlatform, $user->lastAppVersion];
        }

        return [$platform, $client->versionFor($platform)];
    }

    // A new day is always written, so the first minute of a day is never lost to the throttle.
    private function isThrottled(?DateTimeImmutable $lastWrite, DateTimeImmutable $now): bool
    {
        return $lastWrite !== null
            && $lastWrite->format('Y-m-d') === $now->format('Y-m-d')
            && $now->getTimestamp() - $lastWrite->getTimestamp() < self::WRITE_INTERVAL_SECONDS;
    }
}
