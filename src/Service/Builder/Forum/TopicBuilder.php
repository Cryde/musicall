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
        $item = new Topic();
        $item->id = $topic->getId();
        $item->title = $topic->getTitle();
        $item->slug = $topic->getSlug();
        $item->isLocked = $topic->getIsLocked();
        $item->forum = $this->buildForum($topic->getForum());

        return $item;
    }

    private function buildForum(ForumEntity $forum): Forum
    {
        $item = new Forum();
        $item->id = $forum->getId();
        $item->title = $forum->getTitle();
        $item->slug = $forum->getSlug();
        $item->description = $forum->getDescription();

        return $item;
    }
}
