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
    // Engagement Metrics
    public ?float $dauMauRatio = null;

    // Retention Metrics
    public ?float $retention7Days = null;
    public ?float $retention30Days = null;

    // Total counts
    public int $totalUsers = 0;
    public int $totalPublications = 0;
    public int $totalMessages = 0;
}
