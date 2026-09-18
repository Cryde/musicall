<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Repository\Message\MessageMentionRepository;
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
    public function buildFromProjection(array $rows, string $bandSpaceId): array
    {
        // One query for the whole page, never one per message. The lookup lives here rather than in
        // the caller so that no entry point can forget it and quietly render every name as
        // `@inconnu` (#964).
        $mentionsByMessage = $this->messageMentionRepository->findUsernamesByMessageIds(
            array_map(static fn (array $row): string => (string) $row['id'], $rows),
        );

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
            ),
            $rows,
        );
    }

    /**
     * The single message a POST returns. Hydrating one author is not the trap the list is.
     */
    public function buildItem(Message $entity, string $bandSpaceId): ChatMessageResource
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
        );
    }

    /**
     * @param array<string, string> $usernamesById user id => username, for the mentions this message carries
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

        return $dto;
    }
}
