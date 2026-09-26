<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\Message;
use App\Entity\Message\MessageReaction;
use App\Entity\User;
use App\Enum\Message\MessageChange;
use App\Enum\Message\MessageReactionEmoji;
use App\Event\BandSpaceChatMessageChangedEvent;
use App\Repository\Message\MessageReactionRepository;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Takes back one reaction the member left (#968).
 *
 * The emoji arrives as a path segment, so there is nothing for the validator to hold: a slug outside
 * the allow list names a reaction that cannot exist, which is the same 404 as one the member never
 * left. Both answers are identical on purpose, since neither tells the caller anything.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class ChatMessageReactionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
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

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$message instanceof Message) {
            throw new NotFoundHttpException('Message introuvable');
        }

        // Off the request rather than out of $uriVariables: the slug is not one of the resource's
        // identifiers, and API Platform only hands over the variables a Link declares. Same shape as
        // AgendaEntryOccurrenceDeleteProcessor.
        $slug = (string) ($this->requestStack->getCurrentRequest()?->attributes->get('emoji') ?? '');
        $emoji = MessageReactionEmoji::tryFrom($slug);
        $reaction = $emoji === null
            ? null
            : $this->messageReactionRepository->findOneBy(['message' => $message, 'user' => $user, 'emoji' => $emoji]);

        if (!$reaction instanceof MessageReaction) {
            throw new NotFoundHttpException('Réaction introuvable');
        }

        $this->entityManager->remove($reaction);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new BandSpaceChatMessageChangedEvent($message, MessageChange::Reaction));
    }
}
