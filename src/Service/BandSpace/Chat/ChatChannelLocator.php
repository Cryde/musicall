<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The band's channel for a member of it, where every band presence request starts from.
 */
readonly class ChatChannelLocator
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadRepository $messageThreadRepository,
    ) {
    }

    /**
     * checkMember, not ForWrite: being in a chat is reading it, which a space pending deletion allows.
     */
    public function locate(string $bandSpaceId, User $user): MessageThread
    {
        [$bandSpace] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('band_space.chat.no_conversation');
        }

        return $channel;
    }
}
