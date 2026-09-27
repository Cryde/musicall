<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\Recent\RecentForumTopic;
use App\ApiResource\Forum\Recent\RecentForumTopics;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Repository\Forum\ForumTopicRepository;

/**
 * @implements ProviderInterface<RecentForumTopics>
 */
readonly class RecentForumTopicsProvider implements ProviderInterface
{
    private const int LIMIT = 3;

    public function __construct(private ForumTopicRepository $forumTopicRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RecentForumTopics
    {
        $recent = new RecentForumTopics();
        $recent->topics = array_values(array_map(static function (ForumTopic $topic): RecentForumTopic {
            $item = new RecentForumTopic();
            $item->title = $topic->title;
            $item->slug = $topic->slug;
            $item->forumTitle = $topic->forum->title;
            $item->forumSlug = $topic->forum->slug;
            $item->replies = max($topic->postNumber - 1, 0);
            $item->lastActivityDatetime = $topic->lastPost instanceof ForumPost
                ? $topic->lastPost->creationDatetime
                : $topic->creationDatetime;

            return $item;
        }, $this->forumTopicRepository->findRecentlyActive(self::LIMIT)));

        return $recent;
    }
}
