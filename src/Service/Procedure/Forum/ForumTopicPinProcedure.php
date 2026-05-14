<?php

declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\Entity\Forum\ForumTopic;
use Doctrine\ORM\EntityManagerInterface;

readonly class ForumTopicPinProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(ForumTopic $topic, bool $pinned): void
    {
        $targetType = $pinned ? ForumTopic::TYPE_TOPIC_PINNED : ForumTopic::TYPE_TOPIC_DEFAULT;
        if ($topic->type === $targetType) {
            return;
        }

        $topic->type = $targetType;
        $this->entityManager->flush();
    }
}
