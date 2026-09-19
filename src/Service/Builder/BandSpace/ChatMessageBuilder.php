<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\Message\MessageReactionEmoji;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageReactionRepository;
use App\Service\BandSpace\ChatMentionRenderer;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class ChatMessageBuilder
{
    public function __construct(
        #[Target('app.onlybr_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private MessageMentionRepository $messageMentionRepository,
        private ChatMentionRenderer $chatMentionRenderer,
        private MessageReactionRepository $messageReactionRepository,
    ) {
    }

    /**
     * The list, from scalars rather than entities.
     *
     * MessageRepository::findForThread() projects instead of hydrating, so no `User` is loaded and the
     * three profile tables it drags along (#730) stay out of a fifty-message page. That is the whole
     * reason this builder does not simply take Message entities.
     *
     * @param array<int, array{id: string, content: string, creationDatetime: \DateTimeInterface, authorId: string, authorUsername: string, authorDeletionDatetime: ?\DateTimeImmutable, authorProfilePictureName: ?string}> $rows
     *
     * @return ChatMessageResource[]
     */
    public function buildFromProjection(array $rows, string $bandSpaceId, User $viewer): array
    {
        // One query for the whole page, never one per message. The lookup lives here rather than in
        // the caller so that no entry point can forget it and quietly render every name as
        // `@inconnu` (#964).
        $messageIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $mentionsByMessage = $this->messageMentionRepository->findUsernamesByMessageIds($messageIds);
        // Same rule, same reason (#968): one grouped query for the page, here rather than in the
        // caller so no entry point can forget it and silently drop every reaction.
        $reactionsByMessage = $this->messageReactionRepository->findAggregatedByMessageIds($messageIds, $viewer);

        return array_map(
            fn (array $row): ChatMessageResource => $this->build(
                (string) $row['id'],
                $bandSpaceId,
                (string) $row['authorId'],
                (string) $row['authorUsername'],
                $row['authorDeletionDatetime'] !== null,
                $this->profilePictureUrlBuilder->buildFromImageName($row['authorProfilePictureName']),
                (string) $row['content'],
                $row['creationDatetime'],
                $mentionsByMessage[(string) $row['id']] ?? [],
                $reactionsByMessage[(string) $row['id']] ?? [],
            ),
            $rows,
        );
    }

    /**
     * The single message a POST returns. Hydrating one author is not the trap the list is.
     */
    public function buildItem(Message $entity, string $bandSpaceId, User $viewer): ChatMessageResource
    {
        $messageId = (string) $entity->id;

        return $this->build(
            $messageId,
            $bandSpaceId,
            (string) $entity->author->id,
            $entity->author->username,
            $entity->author->isDeleted(),
            $this->profilePictureUrlBuilder->build($entity->author),
            $entity->content,
            $entity->creationDatetime,
            $this->messageMentionRepository->findUsernamesByMessageIds([$messageId])[$messageId] ?? [],
            $this->messageReactionRepository->findAggregatedByMessageIds([$messageId], $viewer)[$messageId] ?? [],
        );
    }

    /**
     * @param array<string, string> $usernamesById user id => username, for the mentions this message carries
     * @param array<string, array{count: int, hasReacted: bool}> $reactionTallies emoji slug => tally
     */
    private function build(
        string $id,
        string $bandSpaceId,
        string $authorId,
        string $authorUsername,
        bool $authorIsDeleted,
        ?string $authorProfilePictureUrl,
        string $content,
        \DateTimeInterface $creationDatetime,
        array $usernamesById,
        array $reactionTallies,
    ): ChatMessageResource {
        $dto = new ChatMessageResource();
        $dto->id = $id;
        $dto->bandSpaceId = $bandSpaceId;
        $dto->authorId = $authorId;
        $dto->authorUsername = $authorIsDeleted ? User::DELETED_DISPLAY_NAME : $authorUsername;
        $dto->authorProfilePictureUrl = $authorProfilePictureUrl;
        // Sanitized at read time, exactly like the direct message thread: what the sender typed stays
        // stored, so changing how a message renders stays possible (#956 was closed on that point).
        // Mentions go in after the sanitizer, never before: by then everything the sender typed is
        // escaped, so the span the renderer adds is the only markup in there and the username inside
        // it is the only thing that still needs escaping. See ChatMentionRenderer.
        $dto->content = $this->chatMentionRenderer->render(
            $this->sanitizer->sanitize(nl2br($content)),
            $usernamesById,
        );
        $dto->creationDatetime = $creationDatetime;
        $dto->reactions = $this->buildReactions($reactionTallies);

        return $dto;
    }

    /**
     * Driven by the enum rather than by the rows, which is what makes the order the declaration order
     * whatever the database returns, and what keeps an emoji nobody used out of the payload.
     *
     * @param array<string, array{count: int, hasReacted: bool}> $reactionTallies
     *
     * @return list<array{key: string, emoji: string, count: int, has_reacted: bool}>
     */
    private function buildReactions(array $reactionTallies): array
    {
        $reactions = [];
        foreach (MessageReactionEmoji::cases() as $emoji) {
            $tally = $reactionTallies[$emoji->value] ?? null;
            if ($tally === null) {
                continue;
            }

            $reactions[] = [
                'key' => $emoji->value,
                'emoji' => $emoji->character(),
                'count' => $tally['count'],
                'has_reacted' => $tally['hasReacted'],
            ];
        }

        return $reactions;
    }
}
