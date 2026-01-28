<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Dashboard\UserDashboardMetrics;
use App\Repository\Message\MessageRepository;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<UserDashboardMetrics>
 */
readonly class UserDashboardMetricsProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageRepository $messageRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserDashboardMetrics
    {
        $metrics = new UserDashboardMetrics();

        $oneDayAgo = new \DateTimeImmutable('-24 hours');
        $sevenDaysAgo = new \DateTimeImmutable('-7 days midnight');
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days midnight');

        // Spam Detection - Recent Empty Accounts (last 24h)
        $emptyProfiles = $this->userRepository->findRecentEmptyProfiles($oneDayAgo, 10);
        foreach ($emptyProfiles as $item) {
            $user = $item['user'];
            $userId = $user->getId();
            if ($userId === null) {
                continue;
            }
            $metrics->recentEmptyAccounts[] = [
                'id' => $userId,
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'registration_date' => $user->getCreationDatetime()->format('Y-m-d H:i'),
                'profile_completion_percent' => $item['profile_completion'],
            ];
        }

        // Spam Detection - Message Spam Ratio (coming soon - requires more complex query)
        $metrics->messageSpamRatio = null;

        // Suspicious Engagement - External Link Posters (coming soon - requires content scanning)
        $metrics->externalLinkPosters = null;

        // Suspicious Engagement - Abnormal Activity Spikes (coming soon - requires hourly tracking)
        $metrics->abnormalActivitySpikes = null;

        // Community Health - Profile Completion Rates
        $stats7Days = $this->userRepository->getProfileCompletionStats($sevenDaysAgo);
        $stats30Days = $this->userRepository->getProfileCompletionStats($thirtyDaysAgo);

        $metrics->profileCompletionRates = [
            'last_7_days' => $stats7Days,
            'last_30_days' => $stats30Days,
        ];

        // Community Health - Top Contributors (coming soon - requires contribution tracking)
        $metrics->topContributors = [];

        // Recent Registrations
        $recentUsers = $this->userRepository->findRecentRegistrationsWithCompletion(5);
        foreach ($recentUsers as $item) {
            $user = $item['user'];
            $userId = $user->getId();
            if ($userId === null) {
                continue;
            }
            $metrics->recentRegistrations[] = [
                'id' => $userId,
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'registration_date' => $user->getCreationDatetime()->format('Y-m-d H:i'),
                'profile_completion_percent' => $item['profile_completion'],
                'first_action' => null, // Coming soon - requires action tracking
            ];
        }

        // Top Messagers (7 days)
        $topMessagers = $this->messageRepository->findTopMessagers($sevenDaysAgo, 5);
        foreach ($topMessagers as $messager) {
            $avgPerDay = $messager['message_count'] / 7;
            $metrics->topMessagers[] = [
                'id' => $messager['user_id'],
                'username' => $messager['username'],
                'message_count' => $messager['message_count'],
                'account_age_days' => $messager['account_age_days'],
                'avg_messages_per_day' => round($avgPerDay, 1),
            ];
        }

        // Totals
        $metrics->totalUsersLast24h = $this->userRepository->countRegistrationsSince($oneDayAgo);
        $metrics->totalUsersLast7Days = $this->userRepository->countRegistrationsSince($sevenDaysAgo);
        $metrics->unconfirmedAccounts = $this->userRepository->countUnconfirmedAccounts();

        return $metrics;
    }
}
