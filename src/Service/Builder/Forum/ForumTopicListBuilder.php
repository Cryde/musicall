<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\ForumPost;
use App\ApiResource\Forum\ForumTopic;
use App\Entity\Forum\ForumPost as ForumPostEntity;
use App\Entity\Forum\ForumTopic as ForumTopicEntity;

readonly class ForumTopicListBuilder
{
    public function __construct(
        private UserDtoBuilder $userDtoBuilder,
    ) {
    }

    /**
     * @param ForumTopicEntity[] $topics
     *
     * @return ForumTopic[]
     */
    public function buildFromEntities(array $topics): array
    {
        return array_map(
            fn (ForumTopicEntity $topic): ForumTopic => $this->buildFromEntity($topic),
            $topics
        );
    }

    public function buildFromEntity(ForumTopicEntity $topic): ForumTopic
    {
        $item = new ForumTopic();
        $item->id = $topic->getId();
        $item->title = $topic->getTitle();
        $item->slug = $topic->getSlug();
        $item->type = $topic->getType();
        $item->isLocked = $topic->getIsLocked();
        $item->lastPost = $topic->getLastPost() ? $this->buildPostSimple($topic->getLastPost()) : null;
        $item->creationDatetime = $topic->getCreationDatetime();
        $item->author = $this->userDtoBuilder->buildFromEntity($topic->getAuthor());
        $item->postNumber = $topic->getPostNumber();

        return $item;
    }

    private function buildPostSimple(ForumPostEntity $post): ForumPost
    {
        $item = new ForumPost();
        $item->id = $post->getId();
        $item->creationDatetime = $post->getCreationDatetime();
        $item->creator = $this->userDtoBuilder->buildFromEntity($post->getCreator());

        return $item;
    }
}
