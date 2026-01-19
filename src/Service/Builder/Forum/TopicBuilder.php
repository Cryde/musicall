<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\Forum;
use App\ApiResource\Forum\Topic;
use App\Entity\Forum\Forum as ForumEntity;
use App\Entity\Forum\ForumTopic as ForumTopicEntity;

readonly class TopicBuilder
{
    public function buildFromEntity(ForumTopicEntity $topic): Topic
    {
        $forum = $topic->getForum();

        $item = new Topic();
        $item->id = (string) $topic->getId();
        $item->title = (string) $topic->getTitle();
        $item->slug = (string) $topic->getSlug();
        $item->isLocked = (bool) $topic->getIsLocked();
        $item->forum = $this->buildForum($forum);

        return $item;
    }

    private function buildForum(ForumEntity $forum): Forum
    {
        $item = new Forum();
        $item->id = (string) $forum->getId();
        $item->title = (string) $forum->getTitle();
        $item->slug = (string) $forum->getSlug();
        $item->description = (string) $forum->getDescription();

        return $item;
    }
}
