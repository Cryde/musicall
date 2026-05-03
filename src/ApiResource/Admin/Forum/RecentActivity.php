<?php declare(strict_types=1);

namespace App\ApiResource\Admin\Forum;

use ApiPlatform\Metadata\Get;
use App\State\Provider\Admin\Forum\RecentActivityProvider;

#[Get(
    uriTemplate: '/admin/forum/recent-activity',
    security: 'is_granted("ROLE_ADMIN")',
    provider: RecentActivityProvider::class,
)]
class RecentActivity
{
    /** @var array<int, array{id: string, slug: string, title: string, creation_datetime: string, author_username: string}> */
    public array $recentTopics = [];

    /** @var array<int, array{id: string, topic_slug: string, topic_title: string, topic_page: int, content_excerpt: string, creation_datetime: string, creator_username: string}> */
    public array $recentPosts = [];
}
