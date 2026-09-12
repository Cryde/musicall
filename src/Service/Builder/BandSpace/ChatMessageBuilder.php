<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class ChatMessageBuilder
{
    public function __construct(
        private HtmlSanitizerInterface $appOnlybrSanitizer,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
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
            ),
            $rows,
        );
    }

    /**
     * The single message a POST returns. Hydrating one author is not the trap the list is.
     */
    public function buildItem(Message $entity, string $bandSpaceId): ChatMessageResource
    {
        return $this->build(
            (string) $entity->id,
            $bandSpaceId,
            (string) $entity->author->id,
            $entity->author->username,
            $entity->author->isDeleted(),
            $this->profilePictureUrlBuilder->build($entity->author),
            $entity->content,
            $entity->creationDatetime,
        );
    }

    private function build(
        string $id,
        string $bandSpaceId,
        string $authorId,
        string $authorUsername,
        bool $authorIsDeleted,
        ?string $authorProfilePictureUrl,
        string $content,
        \DateTimeInterface $creationDatetime,
    ): ChatMessageResource {
        $dto = new ChatMessageResource();
        $dto->id = $id;
        $dto->bandSpaceId = $bandSpaceId;
        $dto->authorId = $authorId;
        $dto->authorUsername = $authorIsDeleted ? User::DELETED_DISPLAY_NAME : $authorUsername;
        $dto->authorProfilePictureUrl = $authorProfilePictureUrl;
        // Sanitized at read time, exactly like the direct message thread: what the sender typed stays
        // stored, so changing how a message renders stays possible (#956 was closed on that point).
        $dto->content = $this->appOnlybrSanitizer->sanitize(nl2br($content));
        $dto->creationDatetime = $creationDatetime;

        return $dto;
    }
}
