<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageUpdate;
use App\Entity\User;
use App\Enum\Message\MessageChange;
use App\Event\MessageChangedEvent;
use App\Security\Message\DirectThreadChecker;
use App\Service\Builder\Message\MessageBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Editing your own message in a direct conversation (#1245). Author only and no time limit, like the
 * band chat (#966). Nothing to reconcile: a direct message carries no mention.
 *
 * @implements ProcessorInterface<MessageUpdate, MessageResource>
 */
readonly class MessageUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private MessageBuilder $messageBuilder,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
    ) {
    }

    /**
     * @param MessageUpdate $data
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
        // A PATCH on a tombstone would not be a correction but a way to write over a deletion.
        if ($message->isDeleted()) {
            throw new NotFoundHttpException('message.message.not_found');
        }
        if ($message->author->id !== $user->id) {
            throw new AccessDeniedHttpException('message.message.update_forbidden');
        }
        if ($message->isVoiceNote()) {
            throw new AccessDeniedHttpException('message.message.voice_note_not_editable');
        }

        // An identical body is not an edit: stamping it would paint « modifié » on an unchanged message.
        if ($data->content !== $message->content) {
            $message->content = $data->content;
            $message->updateDatetime = new \DateTimeImmutable();
            $this->entityManager->flush();
            $this->eventDispatcher->dispatch(new MessageChangedEvent($message, MessageChange::Edit));
        }

        return $this->messageBuilder->buildForViewer([$message], $thread, $user)[0];
    }
}
