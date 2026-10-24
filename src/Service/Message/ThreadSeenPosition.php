<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;

/**
 * How far the other person has seen a direct conversation, which is what « Vu » reads (#1243).
 */
readonly class ThreadSeenPosition
{
    public function __construct(
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private ReadReceiptPolicy $readReceiptPolicy,
    ) {
    }

    /**
     * Null when « Vu » does not apply between the two. One lookup per page: a seen position belongs to
     * the reader, not to a message. Expects the participants loaded, as findOneByIdWithParticipants()
     * does, or this loads the other person and their profile rows on its own.
     */
    public function of(MessageThread $thread, User $viewer): ?\DateTimeImmutable
    {
        foreach ($thread->messageParticipants as $participant) {
            $other = $participant->participant;
            if ($other->id === $viewer->id) {
                continue;
            }
            if (!$this->readReceiptPolicy->appliesBetween($viewer, $other)) {
                return null;
            }
            $meta = $this->messageThreadMetaRepository->findOneBy(['thread' => $thread, 'user' => $other]);

            return $meta instanceof MessageThreadMeta ? $meta->lastSeenDatetime : null;
        }

        return null;
    }
}
