<?php

declare(strict_types=1);

namespace App\ApiResource\Forum\Recent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Forum\RecentForumTopicsProvider;

/**
 * The topics that moved last across every forum (#1078). Public, like the forum lists it summarizes.
 * One object rather than a collection, since its topics already have their own addresses.
 */
#[Get(
    uriTemplate: '/forums/recent-topics',
    openapi: new Operation(tags: ['Forum']),
    name: 'api_forum_recent_topics',
    provider: RecentForumTopicsProvider::class,
)]
class RecentForumTopics
{
    /** @var list<RecentForumTopic> */
    #[ApiProperty(genId: false)]
    public array $topics = [];
}
