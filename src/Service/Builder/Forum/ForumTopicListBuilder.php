<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\ForumPost;
use App\ApiResource\Forum\Data\User;
use App\ApiResource\Forum\ForumTopic;
use App\Entity\Forum\ForumPost as ForumPostEntity;
use App\Entity\Forum\ForumTopic as ForumTopicEntity;
use App\Entity\User as UserEntity;

readonly class ForumTopicListBuilder
{
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
        $item->author = $this->buildUserSimple($topic->getAuthor());
        $item->postNumber = $topic->getPostNumber();

        return $item;
    }

    private function buildPostSimple(ForumPostEntity $post): ForumPost
    {
        $item = new ForumPost();
        $item->id = $post->getId();
        $item->creationDatetime = $post->getCreationDatetime();
        $item->creator = $this->buildUserSimple($post->getCreator());

        return $item;
    }

    private function buildUserSimple(UserEntity $user): User
    {
        $item = new User();
        $item->id = $user->getId();
        $item->username = $user->getUsername();

        return $item;
    }
}
