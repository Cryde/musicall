<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatPresence;
use App\Entity\User;
use App\Security\Message\DirectThreadChecker;
use App\Service\Message\ChatPresenceTracker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Not rate limited, unlike the heartbeat: switching conversations sends a leave per switch, and a
 * refused leave would show the reader in the conversation they left for the whole TTL. It costs
 * nothing to spam either, since only a tab a limited heartbeat put there can be announced leaving.
 *
 * @implements ProcessorInterface<null, void>
 */
readonly class MessageThreadPresenceLeaveProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private ChatPresenceTracker $presenceTracker,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // Refused under a block too, like every conversation write. What it leaves behind is read by
        // nobody, since both sides' heartbeats are refused, and the TTL clears it.
        $thread = $this->directThreadChecker->checkParticipantForWrite((string) $uriVariables['threadId'], $user);

        $this->presenceTracker->leave($thread, $user, ChatPresence::tabOf($operation));
    }
}
