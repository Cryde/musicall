<?php declare(strict_types=1);

namespace App\State\Provider\Admin\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Forum\RecentActivity;
use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Forum\ForumPostExcerpt;

/**
 * @implements ProviderInterface<RecentActivity>
 */
readonly class RecentActivityProvider implements ProviderInterface
{
    private const LIMIT = 10;

    public function __construct(
        private ForumTopicRepository $forumTopicRepository,
        private ForumPostRepository $forumPostRepository,
        private ForumPostExcerpt $forumPostExcerpt,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RecentActivity
    {
        $result = new RecentActivity();

        $result->recentTopics = array_map(
            fn(ForumTopic $topic): array => [
                'id' => (string) $topic->id,
                'slug' => $topic->slug,
                'title' => $topic->title,
                'creation_datetime' => $topic->creationDatetime->format(\DateTimeInterface::ATOM),
                'author_username' => $topic->author->username,
            ],
            $this->forumTopicRepository->findLatest(self::LIMIT),
        );

        $result->recentPosts = array_map(
            fn(ForumPost $post): array => [
                'id' => (string) $post->id,
                'topic_slug' => $post->topic->slug,
                'topic_title' => $post->topic->title,
                'topic_page' => (int) ceil($this->forumPostRepository->findPositionInTopic($post) / ForumPostResource::POSTS_PER_PAGE),
                'content_excerpt' => $this->forumPostExcerpt->create($post->content),
                'creation_datetime' => $post->creationDatetime->format(\DateTimeInterface::ATOM),
                'creator_username' => $post->creator->username,
            ],
            $this->forumPostRepository->findLatest(self::LIMIT),
        );

        return $result;
    }
}
