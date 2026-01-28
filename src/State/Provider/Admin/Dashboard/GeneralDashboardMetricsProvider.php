<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Dashboard\GeneralDashboardMetrics;
use App\Repository\Message\MessageRepository;
use App\Repository\PublicationRepository;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<GeneralDashboardMetrics>
 */
readonly class GeneralDashboardMetricsProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageRepository $messageRepository,
        private PublicationRepository $publicationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GeneralDashboardMetrics
    {
        $metrics = new GeneralDashboardMetrics();

        $now = new \DateTimeImmutable();
        $todayMidnight = new \DateTimeImmutable('today midnight');
        $sevenDaysAgo = new \DateTimeImmutable('-7 days midnight');
        $fourteenDaysAgo = new \DateTimeImmutable('-14 days midnight');
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days midnight');
        $sixtyDaysAgo = new \DateTimeImmutable('-60 days midnight');

        // Activity Trends - Registrations
        $metrics->registrationsToday = $this->userRepository->countRegistrationsSince($todayMidnight);
        $metrics->registrations7Days = $this->userRepository->countRegistrationsSince($sevenDaysAgo);
        $metrics->registrations30Days = $this->userRepository->countRegistrationsSince($thirtyDaysAgo);

        // Calculate trend (compare last 7 days vs previous 7 days)
        $previousWeekRegistrations = $this->userRepository->countRegistrationsSince($fourteenDaysAgo) - $metrics->registrations7Days;
        $metrics->registrationsTrendPercent = $this->calculateTrendPercent($metrics->registrations7Days, $previousWeekRegistrations);

        // Activity Trends - Logins
        $metrics->loginsToday = $this->userRepository->countLoginsSince($todayMidnight);
        $metrics->logins7Days = $this->userRepository->countLoginsSince($sevenDaysAgo);
        $metrics->logins30Days = $this->userRepository->countLoginsSince($thirtyDaysAgo);

        $previousWeekLogins = $this->userRepository->countLoginsSince($fourteenDaysAgo) - $metrics->logins7Days;
        $metrics->loginsTrendPercent = $this->calculateTrendPercent($metrics->logins7Days, $previousWeekLogins);

        // Activity Trends - Messages
        $metrics->messagesToday = $this->messageRepository->countMessagesSince($todayMidnight);
        $metrics->messages7Days = $this->messageRepository->countMessagesSince($sevenDaysAgo);
        $metrics->messages30Days = $this->messageRepository->countMessagesSince($thirtyDaysAgo);

        $previousWeekMessages = $this->messageRepository->countMessagesSince($fourteenDaysAgo) - $metrics->messages7Days;
        $metrics->messagesTrendPercent = $this->calculateTrendPercent($metrics->messages7Days, $previousWeekMessages);

        // Activity Trends - Publications
        $metrics->publicationsToday = $this->publicationRepository->countPublicationsSince($todayMidnight);
        $metrics->publications7Days = $this->publicationRepository->countPublicationsSince($sevenDaysAgo);
        $metrics->publications30Days = $this->publicationRepository->countPublicationsSince($thirtyDaysAgo);

        $previousWeekPublications = $this->publicationRepository->countPublicationsSince($fourteenDaysAgo) - $metrics->publications7Days;
        $metrics->publicationsTrendPercent = $this->calculateTrendPercent($metrics->publications7Days, $previousWeekPublications);

        // Engagement Metrics - DAU/MAU ratio
        $dau = $metrics->loginsToday > 0 ? $metrics->loginsToday : $this->userRepository->countLoginsSince(new \DateTimeImmutable('-1 day'));
        $mau = $metrics->logins30Days;
        $metrics->dauMauRatio = $mau > 0 ? round(($dau / $mau) * 100, 1) : null;

        // Engagement Metrics - Time to first action (coming soon - requires more tracking)
        $metrics->avgTimeToFirstAction = null;

        // Engagement Metrics - Conversation ratio (coming soon - requires more tracking)
        $metrics->conversationRatio = null;

        // Content Overview - Publications by type
        $metrics->publicationsByType = $this->publicationRepository->countBySubCategoryType();

        // Content Overview - Top content this week
        $metrics->topContentThisWeek = $this->publicationRepository->findTopPublicationsByViews($sevenDaysAgo, 5);

        // Content Overview - Popular searches (coming soon - no search logging)
        $metrics->popularSearches = null;

        // Retention Metrics
        $metrics->retention7Days = $this->userRepository->calculateRetentionRate(7);
        $metrics->retention30Days = $this->userRepository->calculateRetentionRate(30);

        // Total counts
        $metrics->totalUsers = $this->userRepository->countTotalUsers();
        $metrics->totalPublications = $this->publicationRepository->countTotalOnlinePublications();
        $metrics->totalMessages = $this->messageRepository->countTotalMessages();

        return $metrics;
    }

    private function calculateTrendPercent(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
