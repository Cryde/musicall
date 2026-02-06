<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Dashboard;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Provider\Admin\Dashboard\UserDashboardMetricsProvider;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

#[Get(
    uriTemplate: '/admin/dashboard/users',
    security: 'is_granted("ROLE_ADMIN")',
    provider: UserDashboardMetricsProvider::class,
    parameters: [
        'from' => new QueryParameter(
            key: 'from',
            constraints: [
                new Sequentially(constraints: [
                    new NotBlank(),
                    new Date(),
                ]),
            ],
        ),
        'to' => new QueryParameter(
            key: 'to',
            constraints: [
                new Sequentially(constraints: [
                    new NotBlank(),
                    new Date(),
                ]),
            ],
        ),
    ],
)]
class UserDashboardMetrics
{
    // Spam Detection - Empty Accounts (no avatar AND empty bio)
    /** @var array<int, array{id: string, username: string, email: string, registration_date: string, profile_completion_percent: int}> */
    public array $recentEmptyAccounts = [];

    // Community Health - Profile Completion Rates
    /** @var array{avg_percent: float, total: int, levels: array{empty: int, basic: int, complete: int}} */
    public array $profileCompletionRates = ['avg_percent' => 0, 'total' => 0, 'levels' => ['empty' => 0, 'basic' => 0, 'complete' => 0]];

    // Recent Registrations
    /** @var array<int, array{id: string, username: string, email: string, registration_date: string, profile_completion_percent: int}> */
    public array $recentRegistrations = [];

    // Top Messagers
    /** @var array<int, array{id: string, username: string, message_count: int, account_age_days: int, avg_messages_per_day: float}> */
    public array $topMessagers = [];

    // Totals for context (global, not date-filtered)
    public int $totalUsers = 0;
    public int $unconfirmedAccounts = 0;
    public int $totalMusicianProfiles = 0;
    public int $totalTeacherProfiles = 0;

    /** @var array<string, int> */
    public array $emailsSentByType = [];
}
