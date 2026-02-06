<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Dashboard\UserDashboardMetrics;
use App\Repository\Message\MessageRepository;
use App\Repository\Musician\MusicianProfileRepository;
use App\Repository\Teacher\TeacherProfileRepository;
use App\Repository\User\UserEmailLogRepository;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<UserDashboardMetrics>
 */
readonly class UserDashboardMetricsProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageRepository $messageRepository,
        private MusicianProfileRepository $musicianProfileRepository,
        private TeacherProfileRepository $teacherProfileRepository,
        private UserEmailLogRepository $userEmailLogRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserDashboardMetrics
    {
        $metrics = new UserDashboardMetrics();

        $from = new \DateTimeImmutable($context['filters']['from']);
        // +1 day so the end date is inclusive
        $to = (new \DateTimeImmutable($context['filters']['to']))->modify('+1 day');

        // Spam Detection - Empty Accounts within date range
        $emptyProfiles = $this->userRepository->findRecentEmptyProfiles($from, $to, 10);
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

        // Community Health - Profile Completion Rates within date range
        $metrics->profileCompletionRates = $this->userRepository->getProfileCompletionStats($from, $to);

        // Recent Registrations within date range
        $recentUsers = $this->userRepository->findRecentRegistrationsWithCompletion($from, $to, 10);
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
            ];
        }

        // Top Messagers within date range
        $rangeDays = max(1, $from->diff($to)->days ?: 1);
        $topMessagers = $this->messageRepository->findTopMessagers($from, $to, 5);
        foreach ($topMessagers as $messager) {
            $avgPerDay = $messager['message_count'] / $rangeDays;
            $metrics->topMessagers[] = [
                'id' => $messager['user_id'],
                'username' => $messager['username'],
                'message_count' => $messager['message_count'],
                'account_age_days' => $messager['account_age_days'],
                'avg_messages_per_day' => round($avgPerDay, 1),
            ];
        }

        // Emails sent by type within date range
        $metrics->emailsSentByType = $this->userEmailLogRepository->countByTypeBetween($from, $to);

        // Totals (global, not date-filtered)
        $metrics->totalUsers = $this->userRepository->countTotalUsers();
        $metrics->unconfirmedAccounts = $this->userRepository->countUnconfirmedAccounts();
        $metrics->totalMusicianProfiles = $this->musicianProfileRepository->countAll();
        $metrics->totalTeacherProfiles = $this->teacherProfileRepository->countAll();

        return $metrics;
    }
}
