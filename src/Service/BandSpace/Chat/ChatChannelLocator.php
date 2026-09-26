<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The band space and its channel for a member of it, the pair every presence request starts from.
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
     *
     * @return array{BandSpace, MessageThread}
     */
    public function locate(string $bandSpaceId, User $user): array
    {
        [$bandSpace] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('Ce Band Space n\'a pas de conversation');
        }

        return [$bandSpace, $channel];
    }
}
