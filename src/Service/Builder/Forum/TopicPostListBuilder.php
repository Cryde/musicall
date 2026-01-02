<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\TopicPost;
use App\Entity\Forum\ForumPost as ForumPostEntity;

readonly class TopicPostListBuilder
{
    public function __construct(
        private UserDtoBuilder $userDtoBuilder,
    ) {
    }

    /**
     * @param ForumPostEntity[] $posts
     *
     * @return TopicPost[]
     */
    public function buildFromEntities(array $posts): array
    {
        return array_map(
            fn (ForumPostEntity $post): TopicPost => $this->buildFromEntity($post),
            $posts
        );
    }

    public function buildFromEntity(ForumPostEntity $post): TopicPost
    {
        $item = new TopicPost();
        $item->id = $post->getId();
        $item->creationDatetime = $post->getCreationDatetime();
        $item->updateDatetime = $post->getUpdateDatetime();
        $item->content = $post->getContent();
        $item->creator = $this->userDtoBuilder->buildFromEntity($post->getCreator());

        return $item;
    }
}
