<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\Forum;
use App\ApiResource\Forum\Data\ForumPost;
use App\ApiResource\Forum\Data\ForumTopicSummary;
use App\ApiResource\Forum\ForumTopicParticipationResource;
use App\Entity\Forum\Forum as ForumEntity;
use App\Entity\Forum\ForumPost as ForumPostEntity;
use App\Entity\Forum\ForumTopic as ForumTopicEntity;
use App\Entity\Forum\ForumTopicParticipation;

readonly class ForumTopicParticipationBuilder
{
    public function __construct(
        private UserDtoBuilder $userDtoBuilder,
    ) {
    }

    /**
     * @param ForumTopicParticipation[] $entities
     *
     * @return ForumTopicParticipationResource[]
     */
    public function buildFromEntities(array $entities): array
    {
        return array_map(
            fn (ForumTopicParticipation $entity): ForumTopicParticipationResource => $this->buildFromEntity($entity),
            $entities
        );
    }

    public function buildFromEntity(ForumTopicParticipation $entity): ForumTopicParticipationResource
    {
        $dto = new ForumTopicParticipationResource();
        $dto->id = (string) $entity->id;
        $dto->isRead = $this->computeIsRead($entity);
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->topic = $this->buildTopicSummary($entity->topic);

        return $dto;
    }

    private function computeIsRead(ForumTopicParticipation $entity): bool
    {
        if ($entity->readDatetime === null) {
            return false;
        }
        $lastPost = $entity->topic->lastPost;
        if (!$lastPost instanceof ForumPostEntity) {
            return true;
        }

        return $entity->readDatetime >= $lastPost->creationDatetime;
    }

    private function buildTopicSummary(ForumTopicEntity $topic): ForumTopicSummary
    {
        $summary = new ForumTopicSummary();
        $summary->id = (string) $topic->id;
        $summary->title = $topic->title;
        $summary->slug = $topic->slug;
        $summary->isLocked = $topic->isLocked;
        $summary->isResolved = $topic->isResolved;
        $summary->isPinned = $topic->type === ForumTopicEntity::TYPE_TOPIC_PINNED;
        $summary->postNumber = $topic->postNumber;
        $summary->creationDatetime = $topic->creationDatetime;
        $summary->author = $this->userDtoBuilder->buildFromEntity($topic->author);
        $summary->forum = $this->buildForum($topic->forum);
        $summary->lastPost = $topic->lastPost instanceof ForumPostEntity ? $this->buildPostSimple($topic->lastPost) : null;

        return $summary;
    }

    private function buildForum(ForumEntity $forum): Forum
    {
        $dto = new Forum();
        $dto->id = (string) $forum->id;
        $dto->title = $forum->title;
        $dto->slug = $forum->slug;
        $dto->description = $forum->description;

        return $dto;
    }

    private function buildPostSimple(ForumPostEntity $post): ForumPost
    {
        $dto = new ForumPost();
        $dto->id = (string) $post->id;
        $dto->creationDatetime = $post->creationDatetime;
        $dto->creator = $this->userDtoBuilder->buildFromEntity($post->creator);

        return $dto;
    }
}
