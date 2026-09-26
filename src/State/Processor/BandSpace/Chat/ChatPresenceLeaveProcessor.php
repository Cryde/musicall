<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatPresence;
use App\Entity\User;
use App\Service\BandSpace\Chat\ChatChannelLocator;
use App\Service\BandSpace\Chat\ChatPresenceTracker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<null, void>
 */
readonly class ChatPresenceLeaveProcessor implements ProcessorInterface
{
    public function __construct(
        private ChatChannelLocator $channelLocator,
        private ChatPresenceTracker $presenceTracker,
        private Security $security,
        #[Target('chat_presence')]
        private RateLimiterFactoryInterface $presenceLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->presenceLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        [$bandSpace, $channel] = $this->channelLocator->locate((string) $uriVariables['bandSpaceId'], $user);

        $this->presenceTracker->leave($bandSpace, $channel, $user, ChatPresence::tabOf($operation));
    }
}
