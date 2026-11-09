<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Dashboard;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Provider\Admin\Dashboard\TimeSeriesMetricsProvider;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

#[Get(
    uriTemplate: '/admin/dashboard/time-series',
    security: 'is_granted("ROLE_ADMIN")',
    provider: TimeSeriesMetricsProvider::class,
    parameters: [
        'metric' => new QueryParameter(
            key: 'metric',
            constraints: [
                new Sequentially(constraints: [
                    new NotBlank(),
                    new Choice(choices: ['registrations', 'registrations_by_platform', 'registrations_by_method', 'logins', 'messages', 'publications', 'comments', 'forum_posts', 'musician_announces', 'musician_searches', 'ai_searches', 'band_spaces', 'band_chat_messages']),
                ]),
            ],
        ),
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
class TimeSeriesMetrics
{
    public string $metric;
    public string $from;
    public string $to;

    /**
     * A segmented metric (`registrations_by_platform`, `registrations_by_method`) adds the `segment` of each count.
     *
     * @var array<int, array{date_label: string, count: int}|array{date_label: string, segment: string, count: int}>
     */
    public array $dataPoints = [];

    public int $total = 0;
}
