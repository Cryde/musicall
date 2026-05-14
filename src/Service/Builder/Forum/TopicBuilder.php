<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\Forum;
use App\ApiResource\Forum\Topic;
use App\Entity\Forum\Forum as ForumEntity;
use App\Entity\Forum\ForumTopic as ForumTopicEntity;

readonly class TopicBuilder
{
    public function __construct(
        private UserDtoBuilder $userDtoBuilder,
    ) {
    }

    public function buildFromEntity(ForumTopicEntity $topic): Topic
    {
        $item = new Topic();
        $item->id = (string) $topic->id;
        $item->title = $topic->title;
        $item->slug = $topic->slug;
        $item->isLocked = $topic->isLocked;
        $item->forum = $this->buildForum($topic->forum);
        $item->author = $this->userDtoBuilder->buildFromEntity($topic->author);

        return $item;
    }

    private function buildForum(ForumEntity $forum): Forum
    {
        $item = new Forum();
        $item->id = (string) $forum->id;
        $item->title = $forum->title;
        $item->slug = $forum->slug;
        $item->description = $forum->description;

        return $item;
    }
}
