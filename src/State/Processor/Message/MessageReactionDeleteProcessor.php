<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\MessageReaction;
use App\Entity\User;
use App\Enum\Message\MessageChange;
use App\Enum\Message\MessageReactionEmoji;
use App\Event\MessageChangedEvent;
use App\Repository\Message\MessageReactionRepository;
use App\Security\Message\DirectThreadChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Takes back one reaction left in a direct conversation (#1245). A slug outside the allow list names
 * a reaction that cannot exist, the same 404 as one never left.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class MessageReactionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private MessageReactionRepository $messageReactionRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [, $message] = $this->directThreadChecker->checkMessageForWrite(
            (string) $uriVariables['threadId'],
            (string) $uriVariables['id'],
            $user,
        );

        // Off the request: the slug is not an identifier, and API Platform only hands over the
        // variables a Link declares.
        $slug = (string) ($this->requestStack->getCurrentRequest()?->attributes->get('emoji') ?? '');
        $emoji = MessageReactionEmoji::tryFrom($slug);
        $reaction = $emoji === null
            ? null
            : $this->messageReactionRepository->findOneBy(['message' => $message, 'user' => $user, 'emoji' => $emoji]);

        if (!$reaction instanceof MessageReaction) {
            throw new NotFoundHttpException('message.message.reaction_not_found');
        }

        $this->entityManager->remove($reaction);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new MessageChangedEvent($message, MessageChange::Reaction));
    }
}
