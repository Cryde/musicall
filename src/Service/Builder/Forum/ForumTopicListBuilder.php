<?php declare(strict_types=1);

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
        $item->id = (string) $topic->id;
        $item->title = $topic->title;
        $item->slug = $topic->slug;
        $item->type = $topic->type;
        $item->isLocked = $topic->isLocked;
        $item->lastPost = $topic->lastPost ? $this->buildPostSimple($topic->lastPost) : null;
        $item->creationDatetime = $topic->creationDatetime;
        $item->author = $this->userDtoBuilder->buildFromEntity($topic->author);
        $item->postNumber = $topic->postNumber;

        return $item;
    }

    private function buildPostSimple(ForumPostEntity $post): ForumPost
    {
        $item = new ForumPost();
        $item->id = (string) $post->id;
        $item->creationDatetime = $post->creationDatetime;
        $item->creator = $this->userDtoBuilder->buildFromEntity($post->creator);

        return $item;
    }
}
