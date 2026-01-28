<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Dashboard;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\Admin\Dashboard\GeneralDashboardMetricsProvider;

#[Get(
    uriTemplate: '/admin/dashboard/general',
    security: 'is_granted("ROLE_ADMIN")',
    provider: GeneralDashboardMetricsProvider::class,
)]
class GeneralDashboardMetrics
{
    // Activity Trends - Registrations
    public int $registrationsToday = 0;
    public int $registrations7Days = 0;
    public int $registrations30Days = 0;
    public ?float $registrationsTrendPercent = null;

    // Activity Trends - Logins
    public int $loginsToday = 0;
    public int $logins7Days = 0;
    public int $logins30Days = 0;
    public ?float $loginsTrendPercent = null;

    // Activity Trends - Messages
    public int $messagesToday = 0;
    public int $messages7Days = 0;
    public int $messages30Days = 0;
    public ?float $messagesTrendPercent = null;

    // Activity Trends - Publications
    public int $publicationsToday = 0;
    public int $publications7Days = 0;
    public int $publications30Days = 0;
    public ?float $publicationsTrendPercent = null;

    // Engagement Metrics
    public ?float $dauMauRatio = null;
    public ?float $avgTimeToFirstAction = null;
    public ?float $conversationRatio = null;

    // Content Overview - Publications by type
    /** @var array<string, int> */
    public array $publicationsByType = [];

    // Content Overview - Top content this week
    /** @var array<int, array{id: int, title: string, views: int, type: string}> */
    public array $topContentThisWeek = [];

    // Content Overview - Popular searches (coming soon)
    /** @var array<int, array{query: string, count: int}>|null */
    public ?array $popularSearches = null;

    // Retention Metrics
    public ?float $retention7Days = null;
    public ?float $retention30Days = null;

    // Total counts for context
    public int $totalUsers = 0;
    public int $totalPublications = 0;
    public int $totalMessages = 0;
}
