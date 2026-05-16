<?php

declare(strict_types=1);

namespace App\Service\Forum;

use App\Entity\Forum\ForumTopic;
use App\Entity\Forum\ForumTopicParticipation;
use App\Entity\User;
use App\Repository\Forum\ForumTopicParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class ForumTopicParticipationService
{
    public function __construct(
        private EntityManagerInterface             $entityManager,
        private ForumTopicParticipationRepository  $participationRepository,
    ) {
    }

    /**
     * Upsert the user's participation row when they post on a topic.
     * Marks it as read (they just acted on it) and restores it if previously removed.
     * Other participants automatically appear unread thanks to the datetime comparison
     * against the topic's lastPost.creationDatetime — no bulk UPDATE needed.
     *
     * Caller is responsible for flushing.
     */
    public function recordPost(User $user, ForumTopic $topic): void
    {
        $participation = $this->participationRepository->findOneByUserAndTopic($user, $topic);
        if (!$participation instanceof ForumTopicParticipation) {
            $participation = new ForumTopicParticipation();
            $participation->user = $user;
            $participation->topic = $topic;
            $this->entityManager->persist($participation);
        }
        $participation->readDatetime = new \DateTime();
        $participation->removedDatetime = null;
    }

    public function markRead(User $user, ForumTopic $topic): void
    {
        $participation = $this->participationRepository->findOneByUserAndTopic($user, $topic);
        if (!$participation instanceof ForumTopicParticipation) {
            return;
        }
        $participation->readDatetime = new \DateTime();
        $this->entityManager->flush();
    }
}
