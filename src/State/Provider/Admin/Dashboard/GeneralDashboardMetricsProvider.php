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

        $todayMidnight = new \DateTimeImmutable('today midnight');
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days midnight');

        // Engagement Metrics - DAU/MAU ratio
        $dau = $this->userRepository->countLoginsSince($todayMidnight);
        if ($dau === 0) {
            $dau = $this->userRepository->countLoginsSince(new \DateTimeImmutable('-1 day'));
        }
        $mau = $this->userRepository->countLoginsSince($thirtyDaysAgo);
        $metrics->dauMauRatio = $mau > 0 ? round(($dau / $mau) * 100, 1) : null;

        // Retention Metrics
        $metrics->retention7Days = $this->userRepository->calculateRetentionRate(7);
        $metrics->retention30Days = $this->userRepository->calculateRetentionRate(30);

        // Total counts
        $metrics->totalUsers = $this->userRepository->countTotalUsers();
        $metrics->totalPublications = $this->publicationRepository->countTotalOnlinePublications();
        $metrics->totalMessages = $this->messageRepository->countTotalMessages();

        return $metrics;
    }
}
