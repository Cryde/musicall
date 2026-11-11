<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Dashboard;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Provider\Admin\Dashboard\PlatformActivityMetricsProvider;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

/**
 * Which platforms members use over a range, and which app versions are still in use (#1370). Every count
 * is of distinct members: one active on several days or platforms counts once.
 */
#[Get(
    uriTemplate: '/admin/dashboard/platform-activity',
    security: 'is_granted("ROLE_ADMIN")',
    provider: PlatformActivityMetricsProvider::class,
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
class PlatformActivityMetrics
{
    public int $activeMembers = 0;

    /**
     * Members active each day per platform, shaped like a segmented TimeSeriesMetrics.
     *
     * @var list<array{date_label: string, segment: string, count: int}>
     */
    public array $dailyActiveMembers = [];

    /** @var array<string, int> keyed by platform, every platform present */
    public array $activeMembersByPlatform = [];

    public int $multiPlatformMembers = 0;

    /** @var array<string, int> keyed by the platform the members registered on, every platform present */
    public array $activeMembersByRegistrationPlatform = [];

    /**
     * Over the last 30 days whatever the range: what decides whether an old version can be dropped.
     *
     * @var list<array{platform: string, version: ?string, members: int}>
     */
    public array $appVersions = [];
}
