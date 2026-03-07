<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceNote as BandSpaceNoteDTO;
use App\Entity\BandSpace\BandSpaceNote as BandSpaceNoteEntity;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class BandSpaceNoteBuilder
{
    public function __construct(
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }
    /**
     * @param BandSpaceNoteEntity[] $entities
     * @return BandSpaceNoteDTO[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(BandSpaceNoteEntity $entity): BandSpaceNoteDTO => $this->buildListItem($entity),
            $entities
        );
    }

    public function buildListItem(BandSpaceNoteEntity $entity): BandSpaceNoteDTO
    {
        $dto = new BandSpaceNoteDTO();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->title = $entity->title;
        $dto->emoji = $entity->emoji;
        $dto->parentId = $entity->parent !== null ? (string) $entity->parent->id : null;
        $dto->position = $entity->position;
        $dto->content = null;
        $dto->hasChildren = !$entity->children->isEmpty();
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    public function buildItem(BandSpaceNoteEntity $entity): BandSpaceNoteDTO
    {
        $dto = $this->buildListItem($entity);
        $dto->content = $this->sanitizeContent($entity->content);

        return $dto;
    }

    /**
     * @param array<string, mixed>|null $content
     * @return array<string, mixed>|null
     */
    private function sanitizeContent(?array $content): ?array
    {
        if ($content === null) {
            return null;
        }

        return $this->sanitizeNode($content);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function sanitizeNode(array $node): array
    {
        if (isset($node['text']) && is_string($node['text'])) {
            $node['text'] = $this->sanitizer->sanitize($node['text']);
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn(array $child): array => $this->sanitizeNode($child),
                $node['content']
            );
        }

        return $node;
    }
}
