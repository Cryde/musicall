<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, ChatMessageResource>
 */
readonly class ChatMessagePinProcessor implements ProcessorInterface
{
    /**
     * A pin list nobody can read at a glance is a second feed, so the channel is capped. Ten is the
     * number a collapsed bar can list without becoming its own scroll.
     */
    private const int MAX_PINNED_MESSAGES = 10;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatMessageResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        // Any active member, not just an admin: the member who knows the rehearsal room door code is
        // rarely the one holding the admin role, and they are the one who needs it findable.
        [$bandSpace] = $this->memberChecker->checkMemberForWrite($bandSpaceId, $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        // A tombstone takes no pin, the same 404 the delete and edit endpoints answer with: there is
        // nothing left to keep at the top, and the bar would carry « Message supprimé » (#967).
        if (!$message instanceof Message || $message->isDeleted()) {
            throw new NotFoundHttpException('Message introuvable');
        }

        // Already pinned is a no-op rather than a conflict: two members reaching for the same message
        // both meant "keep this at the top", and re-stamping it would rewrite who pinned it and move
        // it to the front of the bar for no reason.
        if ($message->pinnedDatetime === null) {
            $this->pin($message, $user);
        }

        return $this->chatMessageBuilder->buildItem($message, $bandSpaceId, $user);
    }

    private function pin(Message $message, User $user): void
    {
        // Counted and written without a lock. Two members pinning at once can take a channel to
        // eleven, which costs one extra line in a bar the members control anyway, and locking the
        // whole channel on every pin to prevent it would be the more expensive mistake.
        if ($this->messageRepository->countPinnedForThread($message->thread) >= self::MAX_PINNED_MESSAGES) {
            throw new ConflictHttpException(sprintf(
                'Cette conversation a déjà %d messages épinglés. Détachez-en un avant d\'en épingler un autre.',
                self::MAX_PINNED_MESSAGES,
            ));
        }

        $message->pinnedDatetime = new \DateTimeImmutable();
        $message->pinnedBy = $user;
        $this->entityManager->flush();
    }
}
