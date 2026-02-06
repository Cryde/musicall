<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Dashboard;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Provider\Admin\Dashboard\ContentOverviewMetricsProvider;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

#[Get(
    uriTemplate: '/admin/dashboard/content-overview',
    security: 'is_granted("ROLE_ADMIN")',
    provider: ContentOverviewMetricsProvider::class,
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
class ContentOverviewMetrics
{
    public string $from;
    public string $to;

    /** @var array<string, int> */
    public array $publicationsByType = [];

    /** @var array<int, array{id: int, title: string, views: int, type: string}> */
    public array $topContent = [];

    /** @var array<string, int> */
    public array $publicationsByFormat = [];

    public int $forumTopicsCount = 0;
    public int $forumPostsCount = 0;

    /** @var array<string, int> */
    public array $announcesByType = [];

    /** @var array<int, array{name: string, count: int}> */
    public array $topInstruments = [];

    /** @var array<int, array{name: string, count: int}> */
    public array $topStyles = [];
}
