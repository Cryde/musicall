<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\Message\MessageReactionEmoji;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageReactionRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\BandSpace\ChatMentionRenderer;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Service\Message\MessageAttachmentResolver;
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
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private MessageAttachmentResolver $messageAttachmentResolver,
        private BandSpaceFileRepository $bandSpaceFileRepository,
    ) {
    }

    /**
     * The list, from scalars rather than entities.
     *
     * MessageRepository::findForThread() projects instead of hydrating, so no `User` is loaded and the
     * three profile tables it drags along (#730) stay out of a fifty-message page. That is the whole
     * reason this builder does not simply take Message entities.
     *
     * @param array<int, array{id: string, content: string, creationDatetime: \DateTimeInterface, updateDatetime: ?\DateTimeImmutable, deletionDatetime: ?\DateTimeImmutable, imageFileId: ?string, voiceNoteFileId: ?string, voiceNoteDurationSeconds: ?int, authorId: string, authorUsername: string, authorDeletionDatetime: ?\DateTimeImmutable, authorProfilePictureName: ?string, pinnedDatetime: ?\DateTimeImmutable, pinnedByUsername: ?string, pinnedByDeletionDatetime: ?\DateTimeImmutable}> $rows
     * @param User $viewer who is reading: it decides both the reaction tallies marked as theirs and
     *                     which rows carry their editable content.
     * @param MessageThread $channel the thread these rows come from, which is what the read positions
     *                               behind « Vu par » are asked for.
     *
     * @return ChatMessageResource[]
     */
    public function buildFromProjection(array $rows, string $bandSpaceId, User $viewer, MessageThread $channel): array
    {
        $viewerId = (string) $viewer->id;
        $messageIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);

        // One query for the whole page, never one per message. The lookup lives here rather than in
        // the caller so that no entry point can forget it and quietly render every name as
        // `@inconnu` (#964).
        $mentionsByMessage = $this->messageMentionRepository->findUsernamesByMessageIds($messageIds);
        // Same rule, same reason (#968): one grouped query for the page, here rather than in the
        // caller so no entry point can forget it and silently drop every reaction.
        $reactionsByMessage = $this->messageReactionRepository->findAggregatedByMessageIds($messageIds, $viewer);
        // Same rule, and the same reason it lives here: one query for the page's rows plus one per
        // kind of target present, never one per message (#970).
        $attachmentsByMessage = $this->messageAttachmentResolver->resolveForMessages($messageIds, $bandSpaceId);
        // And again, for the same reason (#977). One query for the page whatever it holds: a read
        // position is per member, so the rows are compared against each message here rather than
        // asked for again.
        $readPositions = $this->readPositionsForPage($channel, array_column($rows, 'creationDatetime'));
        // And for the media (#973, #974): one query for whichever files the page's messages carry.
        $mediaFileIds = [];
        foreach ($rows as $row) {
            foreach ([$row['imageFileId'], $row['voiceNoteFileId']] as $fileId) {
                if ($fileId !== null) {
                    $mediaFileIds[] = (string) $fileId;
                }
            }
        }
        $liveMediaFileIds = $this->bandSpaceFileRepository->findLiveIdsAmong($mediaFileIds, $bandSpaceId);

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
                $attachmentsByMessage[(string) $row['id']] ?? [],
                $row['updateDatetime'],
                $this->editableContentFor((string) $row['authorId'], $viewerId, (string) $row['content'], $row['deletionDatetime'] !== null),
                $row['deletionDatetime'] !== null,
                $row['pinnedDatetime'],
                $row['pinnedByUsername'],
                $row['pinnedByDeletionDatetime'] !== null,
                $this->readersOf($readPositions, $row['creationDatetime'], (string) $row['authorId'], $row['deletionDatetime'] !== null),
                $this->imageOf($row['imageFileId'] !== null ? (string) $row['imageFileId'] : null, $liveMediaFileIds),
                $this->voiceNoteOf(
                    $row['voiceNoteFileId'] !== null ? (string) $row['voiceNoteFileId'] : null,
                    $row['voiceNoteDurationSeconds'],
                    $liveMediaFileIds,
                ),
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
        $imageFileId = $entity->imageFileId !== null ? (string) $entity->imageFileId : null;
        $voiceNoteFileId = $entity->voiceNoteFileId !== null ? (string) $entity->voiceNoteFileId : null;
        $liveMediaFileIds = $this->bandSpaceFileRepository->findLiveIdsAmong(
            array_values(array_filter([$imageFileId, $voiceNoteFileId], static fn (?string $id): bool => $id !== null)),
            $bandSpaceId,
        );

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
            $this->messageAttachmentResolver->resolveForMessages([$messageId], $bandSpaceId)[$messageId] ?? [],
            $entity->updateDatetime,
            $this->editableContentFor((string) $entity->author->id, (string) $viewer->id, $entity->content, $entity->isDeleted()),
            $entity->isDeleted(),
            $entity->pinnedDatetime,
            $entity->pinnedBy?->username,
            $entity->pinnedBy?->isDeleted() ?? false,
            $this->readersOf(
                $this->messageThreadMetaRepository->findReadPositionsForChannel($entity->thread, $entity->creationDatetime),
                $entity->creationDatetime,
                (string) $entity->author->id,
                $entity->isDeleted(),
            ),
            $this->imageOf($imageFileId, $liveMediaFileIds),
            $this->voiceNoteOf($voiceNoteFileId, $entity->voiceNoteDurationSeconds, $liveMediaFileIds),
        );
    }

    /**
     * @param list<string> $availableFileIds the files still live, out of those the page asked about
     *
     * @return array{file_id: string, is_available: bool}|null
     */
    private function imageOf(?string $fileId, array $availableFileIds): ?array
    {
        return $fileId === null ? null : [
            'file_id' => $fileId,
            'is_available' => in_array($fileId, $availableFileIds, true),
        ];
    }

    /**
     * @param list<string> $availableFileIds the files still live, out of those the page asked about
     *
     * @return array{file_id: string, duration_seconds: int, is_available: bool}|null
     */
    private function voiceNoteOf(?string $fileId, ?int $durationSeconds, array $availableFileIds): ?array
    {
        return $fileId === null ? null : [
            'file_id' => $fileId,
            'duration_seconds' => $durationSeconds ?? 0,
            'is_available' => in_array($fileId, $availableFileIds, true),
        ];
    }

    /**
     * The channel's read positions, bounded to the oldest message on the page: an older position has
     * read nothing on it, and an empty page asks nothing at all.
     *
     * @param list<\DateTimeInterface> $creationDatetimes every message on the page
     *
     * @return list<array{userId: string, username: string, lastReadDatetime: \DateTimeImmutable}>
     */
    private function readPositionsForPage(MessageThread $channel, array $creationDatetimes): array
    {
        return $creationDatetimes === []
            ? []
            : $this->messageThreadMetaRepository->findReadPositionsForChannel($channel, min($creationDatetimes));
    }

    /**
     * Who, of those positions, has read this one message.
     *
     * At or after, not after, because that is the exact complement of the unread rule the counters
     * use (`creationDatetime > lastReadDatetime` is unread), and both columns are second granular:
     * anything else would have a message count as unread and as read at the same time.
     *
     * The author is never a reader of their own message, and a tombstone reports nobody: « Vu par »
     * under « Message supprimé » would be about content that no longer exists (#967).
     *
     * @param list<array{userId: string, username: string, lastReadDatetime: \DateTimeImmutable}> $positions
     *
     * @return list<string>
     */
    private function readersOf(array $positions, \DateTimeInterface $creationDatetime, string $authorId, bool $isDeleted): array
    {
        if ($isDeleted) {
            return [];
        }

        $usernames = [];
        foreach ($positions as $position) {
            if ($position['userId'] !== $authorId && $position['lastReadDatetime'] >= $creationDatetime) {
                $usernames[] = $position['username'];
            }
        }

        return $usernames;
    }

    /**
     * The stored text, but only back to the member who wrote it: it is what the edit box is seeded
     * with, and only its author may PATCH it (#966). A deleted account never reads anything, so the
     * `Utilisateur supprimé` substitution above cannot be undone through here.
     */
    private function editableContentFor(string $authorId, string $viewerId, string $content, bool $isDeleted): ?string
    {
        return !$isDeleted && $authorId === $viewerId ? $content : null;
    }

    /**
     * @param array<string, string> $usernamesById user id => username, for the mentions this message carries
     * @param array<string, array{count: int, hasReacted: bool}> $reactionTallies emoji slug => tally
     * @param list<array{type: string, target_id: string, label: string, is_available: bool}> $attachments
     * @param list<string> $readByUsernames
     * @param array{file_id: string, is_available: bool}|null $image
     * @param array{file_id: string, duration_seconds: int, is_available: bool}|null $voiceNote
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
        array $attachments = [],
        ?\DateTimeInterface $updateDatetime = null,
        ?string $editableContent = null,
        bool $isDeleted = false,
        ?\DateTimeInterface $pinnedDatetime = null,
        ?string $pinnedByUsername = null,
        bool $pinnedByIsDeleted = false,
        array $readByUsernames = [],
        ?array $image = null,
        ?array $voiceNote = null,
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
        $dto->attachments = $attachments;
        $dto->updateDatetime = $updateDatetime;
        // Raw on purpose, where `content` above is rendered: MentionEditor round trips the stored
        // `@[uuid]` format, so this is the only shape an edit box can be seeded from.
        $dto->editableContent = $editableContent;
        $dto->isDeleted = $isDeleted;
        // The timestamp is the single source of truth for both: a pin can only be described by the
        // row that carries it, so the flag cannot disagree with the date.
        $dto->isPinned = $pinnedDatetime !== null;
        $dto->pinnedDatetime = $pinnedDatetime;
        $dto->pinnedByUsername = $pinnedByUsername === null
            ? null
            : ($pinnedByIsDeleted ? User::DELETED_DISPLAY_NAME : $pinnedByUsername);
        $dto->readByUsernames = $readByUsernames;
        // Counted from the list rather than queried, so the two cannot disagree.
        $dto->readCount = count($readByUsernames);
        $dto->image = $image;
        $dto->voiceNote = $voiceNote;

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
