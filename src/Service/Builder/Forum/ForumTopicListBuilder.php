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
        $author = $topic->getAuthor();

        $item = new ForumTopic();
        $item->id = (string) $topic->getId();
        $item->title = (string) $topic->getTitle();
        $item->slug = (string) $topic->getSlug();
        $item->type = (int) $topic->getType();
        $item->isLocked = (bool) $topic->getIsLocked();
        $item->lastPost = $topic->getLastPost() ? $this->buildPostSimple($topic->getLastPost()) : null;
        $item->creationDatetime = $topic->getCreationDatetime();
        $item->author = $this->userDtoBuilder->buildFromEntity($author);
        $item->postNumber = (int) $topic->getPostNumber();

        return $item;
    }

    private function buildPostSimple(ForumPostEntity $post): ForumPost
    {
        $creator = $post->getCreator();

        $item = new ForumPost();
        $item->id = (string) $post->getId();
        $item->creationDatetime = $post->getCreationDatetime();
        $item->creator = $this->userDtoBuilder->buildFromEntity($creator);

        return $item;
    }
}
