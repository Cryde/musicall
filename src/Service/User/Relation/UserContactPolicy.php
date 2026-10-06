<?php declare(strict_types=1);

namespace App\Service\User\Relation;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\User\Relation\UserBlockRepository;

/**
 * Whether two users may reach each other (#1117). A block stops contact both ways, whoever placed it;
 * a Band Space is outside this, since its admin is who decides who belongs to it.
 */
readonly class UserContactPolicy
{
    public const string BLOCKED_MESSAGE = 'Vous ne pouvez pas échanger de messages avec cet utilisateur';

    public function __construct(
        private UserBlockRepository $userBlockRepository,
    ) {
    }

    public function canContact(User $first, User $second): bool
    {
        return !$this->userBlockRepository->isBlockedEitherWay($first, $second);
    }

    /** A band channel is always open to its members; a direct thread closes once its two people are blocked. */
    public function canPostInThread(MessageThread $thread, User $sender): bool
    {
        if ($thread->isChannel()) {
            return true;
        }

        foreach ($thread->messageParticipants as $messageParticipant) {
            $participant = $messageParticipant->participant;
            if ($participant->id !== $sender->id && !$this->canContact($sender, $participant)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The recipients the actor is not blocked with, for producers fanning out a notification.
     *
     * @param iterable<User> $recipients
     *
     * @return list<User>
     */
    public function withoutBlocked(User $actor, iterable $recipients): array
    {
        $blockedIds = $this->userBlockRepository->findIdsBlockedEitherWay($actor);

        $contactable = [];
        foreach ($recipients as $recipient) {
            if (!isset($blockedIds[(string) $recipient->id])) {
                $contactable[] = $recipient;
            }
        }

        return $contactable;
    }
}
