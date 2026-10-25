<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\Message\MessageChange;
use App\Event\MessageChangedEvent;
use App\Security\Message\DirectThreadChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Deleting your own message in a direct conversation (#1245). Author only, since a conversation has
 * no admin. A tombstone rather than a removal, like the band chat (#967): a hole reads as a bug, and
 * message_thread.last_message_id may point here. The content goes, emptied on the row.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class MessageDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private EntityManagerInterface $entityManager,
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
        // Deleting again is a miss, answered before the author check: both people already see the
        // tombstone, so there is nothing for a 403 to protect.
        if ($message->isDeleted()) {
            throw new NotFoundHttpException('message.message.not_found');
        }
        if ($message->author->id !== $user->id) {
            throw new AccessDeniedHttpException('message.message.delete_forbidden');
        }

        $message->deletionDatetime = new \DateTimeImmutable();
        $message->content = '';
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new MessageChangedEvent($message, MessageChange::Delete));
    }
}
