<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Dashboard;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\Admin\Dashboard\UserDashboardMetricsProvider;

#[Get(
    uriTemplate: '/admin/dashboard/users',
    security: 'is_granted("ROLE_ADMIN")',
    provider: UserDashboardMetricsProvider::class,
)]
class UserDashboardMetrics
{
    // Spam Detection - Empty Accounts (registered last 24h, no avatar AND empty bio)
    /** @var array<int, array{id: string, username: string, email: string, registration_date: string, profile_completion_percent: int}> */
    public array $recentEmptyAccounts = [];

    // Spam Detection - Message Spam Ratio (sent 50+ messages, received < 5)
    /** @var array<int, array{id: string, username: string, messages_sent: int, messages_received: int, ratio: float}>|null */
    public ?array $messageSpamRatio = null;

    // Suspicious Engagement - External Link Posters
    /** @var array<int, array{id: string, username: string, account_age_days: int, external_links_count: int, last_link_domain: string}>|null */
    public ?array $externalLinkPosters = null;

    // Suspicious Engagement - Abnormal Activity Spikes
    /** @var array<int, array{id: string, username: string, account_age_days: int, peak_messages_per_hour: int, peak_datetime: string}>|null */
    public ?array $abnormalActivitySpikes = null;

    // Community Health - Profile Completion Rates
    /** @var array{last_7_days: array{avg_percent: float, total: int, levels: array{empty: int, basic: int, complete: int}}, last_30_days: array{avg_percent: float, total: int, levels: array{empty: int, basic: int, complete: int}}} */
    public array $profileCompletionRates = [
        'last_7_days' => ['avg_percent' => 0, 'total' => 0, 'levels' => ['empty' => 0, 'basic' => 0, 'complete' => 0]],
        'last_30_days' => ['avg_percent' => 0, 'total' => 0, 'levels' => ['empty' => 0, 'basic' => 0, 'complete' => 0]],
    ];

    // Community Health - Top Contributors
    /** @var array<int, array{id: string, username: string, contributions: int, last_login: string|null, status: string}> */
    public array $topContributors = [];

    // Recent Registrations
    /** @var array<int, array{id: string, username: string, email: string, registration_date: string, profile_completion_percent: int, first_action: string|null}> */
    public array $recentRegistrations = [];

    // Top Messagers (7 days)
    /** @var array<int, array{id: string, username: string, message_count: int, account_age_days: int, avg_messages_per_day: float}> */
    public array $topMessagers = [];

    // Totals for context
    public int $totalUsersLast24h = 0;
    public int $totalUsersLast7Days = 0;
    public int $unconfirmedAccounts = 0;
}
