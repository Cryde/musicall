<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatPresence;
use App\ApiResource\Message\MessageThreadPresence;
use App\Entity\User;
use App\Security\Message\DirectThreadChecker;
use App\Service\Message\ChatPresenceTracker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<null, MessageThreadPresence>
 */
readonly class MessageThreadPresenceProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private ChatPresenceTracker $presenceTracker,
        private Security $security,
        #[Target('chat_presence')]
        private RateLimiterFactoryInterface $presenceLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageThreadPresence
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->presenceLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        // A block closes presence both ways, as it closes typing and « Vu ».
        $threadId = (string) $uriVariables['threadId'];
        $thread = $this->directThreadChecker->checkParticipantForWrite($threadId, $user);

        $presence = new MessageThreadPresence();
        $presence->threadId = $threadId;
        $presence->onlineUserIds = $this->presenceTracker->beat($thread, $user, ChatPresence::tabOf($operation));

        return $presence;
    }
}
