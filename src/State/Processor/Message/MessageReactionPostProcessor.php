<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageReactionResource;
use App\ApiResource\Message\MessageResource;
use App\Entity\User;
use App\Enum\Message\MessageChange;
use App\Enum\Message\MessageReactionEmoji;
use App\Event\MessageChangedEvent;
use App\Repository\Message\MessageReactionRepository;
use App\Security\Message\DirectThreadChecker;
use App\Service\Builder\Message\MessageBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Leaves one reaction on a message of a direct conversation (#1245). Idempotent, and no notification,
 * for the reasons ChatMessageReactionPostProcessor gives.
 *
 * @implements ProcessorInterface<MessageReactionResource, MessageResource>
 */
readonly class MessageReactionPostProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private MessageReactionRepository $messageReactionRepository,
        private MessageBuilder $messageBuilder,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
    ) {
    }

    /**
     * @param MessageReactionResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$thread, $message] = $this->directThreadChecker->checkMessageForWrite(
            (string) $uriVariables['threadId'],
            (string) $uriVariables['id'],
            $user,
        );

        // from(), not tryFrom(): Assert\Choice on the input has already refused anything else.
        $added = $this->messageReactionRepository->add($message, $user, MessageReactionEmoji::from($data->emoji));
        $result = $this->messageBuilder->buildForViewer([$message], $thread, $user)[0];

        // The repeat is a no-op, and a no-op tells nobody.
        if ($added) {
            $this->eventDispatcher->dispatch(new MessageChangedEvent($message, MessageChange::Reaction));
        }

        return $result;
    }
}
