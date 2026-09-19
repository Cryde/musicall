<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\ApiResource\BandSpace\Chat\ChatMessageUpdate;
use App\Entity\Message\Message;
use App\Entity\Message\MessageMention;
use App\Entity\User;
use App\Event\BandSpaceChatMentionedEvent;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\ChatMentionResolver;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Editing your own chat message (#966).
 *
 * No time limit, because no other surface in the product has one, and author only, which is the rule
 * TaskCommentUpdateProcessor already applies to a comment. An admin cannot rewrite what somebody else
 * said: that would be a different feature and a worse one.
 *
 * @implements ProcessorInterface<ChatMessageUpdate, ChatMessageResource>
 */
readonly class ChatMessageUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
        private MessageMentionRepository $messageMentionRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private ChatMentionResolver $chatMentionResolver,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
    ) {
    }

    /**
     * @param ChatMessageUpdate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatMessageResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace] = $this->memberChecker->checkMemberForWrite($bandSpaceId, $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        // A tombstone takes no edit, the same 404 the delete endpoint answers with: its content is
        // already gone, so a PATCH would not be a correction but a way to write over a deletion.
        if (!$message instanceof Message || $message->isDeleted()) {
            throw new NotFoundHttpException('Message introuvable');
        }

        if ((string) $message->author->id !== (string) $user->id) {
            throw new AccessDeniedHttpException('Seul l\'auteur peut modifier ce message');
        }

        // An identical body is not an edit. Stamping it would paint « modifié » on a message nobody
        // changed, and that marker is a claim about the message rather than a detail of it.
        if ($data->content === $message->content) {
            return $this->chatMessageBuilder->buildItem($message, $bandSpaceId, $user);
        }

        $message->content = $data->content;
        $message->updateDatetime = new \DateTimeImmutable();
        $newlyMentionedUsers = $this->reconcileMentions(
            $message,
            $data->content,
            $this->chatMentionResolver->resolve($bandSpace, $data->content),
        );

        // One flush for the text and the rows that name people in it, and no try/catch around the
        // second half. The send path is best-effort because the message is already committed in its
        // own transaction by the time the mentions are written, so failing the request there would
        // report a message everybody can see as not sent; here it is one request, so the pair really
        // can be atomic and a half-applied edit is the thing worth avoiding. The price is accepted
        // rather than overlooked: the same author editing the same message from two clients at once
        // can have both writes decide a member needs a row, and the loser hits
        // `message_mention_unique` and 500s instead of quietly saving half an edit.
        $this->entityManager->flush();

        // Built after the flush, so the names come from the rows this edit just wrote, and before the
        // dispatch, so a listener cannot change what the author is answered with.
        $result = $this->chatMessageBuilder->buildItem($message, $bandSpaceId, $user);

        if ($newlyMentionedUsers !== []) {
            $this->eventDispatcher->dispatch(new BandSpaceChatMentionedEvent($message, $bandSpace, $newlyMentionedUsers));
        }

        return $result;
    }

    /**
     * Brings the message's mention rows back in line with what it now says, and hands back the
     * members it records for the first time.
     *
     * The rows have to move with the content: ChatMentionRenderer names people from them, so one left
     * behind by an edit is a name printed over a token the author deleted, and a missing one is
     * `@inconnu`.
     *
     * Who to notify falls out of the same pass, rather than from resolving the previous text a second
     * time, and that is not a tidying-up: resolve() always reads today's roster, so re-resolving the
     * old text answers "who would this notify now" instead of "who was told". A member named by a
     * token the edit never touched, who has joined the band since the message was written, would come
     * back in both sets, cancel out, and end up with a row and no notification at all. The row is the
     * durable record of having been named, so somebody who already has one was told and somebody who
     * gains one has not been. Same reasoning as TaskCommentMentionRecorder, which sidesteps it by
     * diffing raw tokens before resolving anything.
     *
     * @param User[] $mentionedUsers the active members the new content names
     *
     * @return User[] those of them this write records for the first time
     */
    private function reconcileMentions(Message $message, string $content, array $mentionedUsers): array
    {
        $existingByUserId = [];
        foreach ($this->messageMentionRepository->findByMessage($message) as $mention) {
            $existingByUserId[(string) $mention->mentionedUser->id] = $mention;
        }

        foreach ($existingByUserId as $userId => $mention) {
            if (!$this->stillNames($content, $userId)) {
                $this->entityManager->remove($mention);
            }
        }

        $newlyMentioned = [];
        foreach ($mentionedUsers as $mentionedUser) {
            if (!isset($existingByUserId[(string) $mentionedUser->id])) {
                $this->entityManager->persist(new MessageMention($message, $mentionedUser));
                $newlyMentioned[] = $mentionedUser;
            }
        }

        return $newlyMentioned;
    }

    /**
     * Asked of the tokens rather than of the resolved members, and that difference is the whole
     * reason this is a method. resolve() deliberately answers with active members only, so deleting
     * every row it does not mention would drop the row of somebody who has since left the band while
     * the text still names them, and their name in history would turn into `@inconnu`.
     */
    private function stillNames(string $content, string $userId): bool
    {
        return $this->chatMentionResolver->mentionsEveryone($content)
            || mb_stripos($content, '@[' . $userId . ']') !== false;
    }

}
