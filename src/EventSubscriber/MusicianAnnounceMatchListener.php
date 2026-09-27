<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Enum\User\UserEmailType;
use App\Event\MusicianAnnouncePostedEvent;
use App\Model\Musician\AnnounceMatch;
use App\Service\Mail\Brevo\Musician\AnnounceMatchEmail;
use App\Service\Musician\Match\AnnounceHeadline;
use App\Service\Musician\Match\AnnounceMatcher;
use App\Service\Notification\NotificationCreator;
use App\Service\User\UserEmailLogService;
use App\Service\User\UserNotificationPreferenceChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Tells the members whose announce a new one answers (#1082): a bell notification each time, an email
 * at most once a day. Best effort per the epic #689 contract: dispatched once the announce is saved,
 * every failure is logged and swallowed so it can never fail the post. The payload carries all it
 * shows and links to the author, so it still reads right once the announce is deleted: no enricher.
 */
#[AsEventListener]
readonly class MusicianAnnounceMatchListener
{
    private const string EMAIL_INTERVAL = '-24 hours';

    public function __construct(
        private AnnounceMatcher $announceMatcher,
        private NotificationCreator $notificationCreator,
        private AnnounceMatchEmail $announceMatchEmail,
        private UserEmailLogService $userEmailLogService,
        private UserNotificationPreferenceChecker $preferenceChecker,
        private RouterInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MusicianAnnouncePostedEvent $event): void
    {
        try {
            $matches = $this->announceMatcher->answeredBy($event->announce);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to match a new musician announce', [
                'announce_id' => (string) $event->announce->id,
                'exception' => $e,
            ]);

            return;
        }

        foreach ($matches as $match) {
            $this->notify($match);
            $this->email($match);
        }
    }

    private function notify(AnnounceMatch $match): void
    {
        $announce = $match->announce;
        try {
            $this->notificationCreator->create($match->answered->author, NotificationType::MusicianAnnounceMatch, [
                'announce_id' => (string) $announce->id,
                'announce_type' => $announce->type,
                'instrument_name' => $announce->instrument->musicianName,
                'location_name' => $announce->locationName,
                'distance_km' => $this->distanceKm($match),
                'answered_announce_id' => (string) $match->answered->id,
                'answered_headline' => AnnounceHeadline::of($match->answered),
                'actor_id' => (string) $announce->author->id,
                'actor_username' => $announce->author->username,
                'actor_has_musician_profile' => $announce->author->musicianProfile instanceof MusicianProfile,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create a musician announce match notification', [
                'announce_id' => (string) $announce->id,
                'exception' => $e,
            ]);
        }
    }

    private function email(AnnounceMatch $match): void
    {
        $recipient = $match->answered->author;
        $announce = $match->announce;
        try {
            if (!$this->preferenceChecker->canReceiveAnnounceMatchNotification($recipient)
                || $this->userEmailLogService->hasBeenSentSince($recipient, UserEmailType::ANNOUNCE_MATCH, new \DateTimeImmutable(self::EMAIL_INTERVAL))) {
                return;
            }

            $this->announceMatchEmail->send(
                $recipient->email,
                $recipient->username,
                $announce->author->username,
                AnnounceHeadline::of($announce),
                $announce->locationName,
                $this->distanceKm($match),
                AnnounceHeadline::of($match->answered),
                $this->profileUrl($announce->author),
            );
            $this->userEmailLogService->log($recipient, UserEmailType::ANNOUNCE_MATCH, (string) $announce->id);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send a musician announce match email', [
                'announce_id' => (string) $announce->id,
                'exception' => $e,
            ]);
        }
    }

    // A match a few hundred metres away reads « à 1 km », not « à 0 km ».
    private function distanceKm(AnnounceMatch $match): int
    {
        return max(1, (int) round($match->distanceMetres / 1000));
    }

    // The musician profile when there is one, as the announce cards link.
    private function profileUrl(User $author): string
    {
        $path = 'u/' . rawurlencode($author->username) . ($author->musicianProfile instanceof MusicianProfile ? '/musician' : '');

        return $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . $path;
    }
}
