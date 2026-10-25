<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Event\MessageThreadTypingEvent;
use App\Security\Message\DirectThreadChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<null, void>
 */
readonly class MessageThreadTypingProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
        #[Target('chat_typing')]
        private RateLimiterFactoryInterface $typingLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // The band chat's budget: one person types in one place at a time.
        $this->typingLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        // Somebody who cannot write must not look like they are writing, so a block refuses this too.
        $thread = $this->directThreadChecker->checkParticipantForWrite((string) $uriVariables['threadId'], $user);

        $this->eventDispatcher->dispatch(new MessageThreadTypingEvent($thread, $user));
    }
}
