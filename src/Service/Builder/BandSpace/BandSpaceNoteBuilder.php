<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceNote as BandSpaceNoteDTO;
use App\Entity\BandSpace\BandSpaceNote as BandSpaceNoteEntity;

readonly class BandSpaceNoteBuilder
{
    /**
     * The only values in a stored note a browser ever dereferences: an image node's `src` and a link
     * mark's `href`. A text node is not one of them, which is why text is handed back byte for byte.
     * TipTap renders it as a DOM text node, so `C'est l'heure` is prose and never markup, and passing
     * it through an HtmlSanitizer only entity encoded the apostrophe into the note the member reads.
     */
    private const array URI_ATTRIBUTES = ['src', 'href'];

    /**
     * An allowlist, so a scheme nobody thought of is dropped rather than cleaned. Note images are
     * stored as same origin download paths, which is the leading slash branch. Links are typed by
     * hand, so http(s) and mailto cover what a member can produce. `data:` is refused alongside
     * `javascript:` and `vbscript:` because the editor configures the image extension with
     * `allowBase64: false`, so no note holds an inline image to begin with.
     *
     * A leading slash may not be followed by a slash or a backslash, and no backslash is tolerated
     * anywhere: the WHATWG URL parser normalises `\` to `/` on a special scheme, so `/\evil.example`
     * resolves exactly like the protocol relative `//evil.example` while still reading as an
     * internal path. Both have to go, or a member could point a note image at any host they like.
     */
    private const string SAFE_URI_PATTERN = '#^(?:https?://|mailto:|/(?![\\\\/]))[^\s<>"\'\\\\]*$#i';

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
        $dto->parentId = $entity->parent instanceof \App\Entity\BandSpace\BandSpaceNote ? (string) $entity->parent->id : null;
        $dto->position = $entity->position;
        $dto->content = null;
        $dto->hasChildren = !$entity->children->isEmpty();
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }

    public function buildItem(BandSpaceNoteEntity $entity): BandSpaceNoteDTO
    {
        $dto = $this->buildListItem($entity);
        $dto->content = $this->removeUnsafeUris($entity->content);

        return $dto;
    }

    /**
     * @param array<string, mixed>|null $content
     * @return array<string, mixed>|null
     */
    private function removeUnsafeUris(?array $content): ?array
    {
        // The root is checked like any other node, so "nothing in the returned tree carries a URI the
        // app could not have produced" holds without an exception at the top.
        if ($content === null || !$this->hasSafeUris($content)) {
            return null;
        }

        return $this->removeUnsafeUrisFromNode($content);
    }

    /**
     * A node or a mark carrying a URI the app could never have produced is dropped whole rather than
     * emptied: an image without a `src` is nothing, and dropping a link mark leaves the words the
     * member typed on the page while the unusable target never reaches the DOM.
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function removeUnsafeUrisFromNode(array $node): array
    {
        if (isset($node['marks']) && is_array($node['marks'])) {
            $node['marks'] = array_values(array_filter($node['marks'], $this->hasSafeUris(...)));
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn(array $child): array => $this->removeUnsafeUrisFromNode($child),
                array_values(array_filter($node['content'], $this->hasSafeUris(...)))
            );
        }

        return $node;
    }

    /** @param mixed $element a node or a mark; anything that is not shaped like one is refused */
    private function hasSafeUris(mixed $element): bool
    {
        if (!is_array($element)) {
            return false;
        }

        $attributes = $element['attrs'] ?? null;
        if (!is_array($attributes)) {
            return true;
        }

        foreach (self::URI_ATTRIBUTES as $name) {
            $uri = $attributes[$name] ?? null;
            if ($uri === null) {
                continue;
            }

            // Anything that is not a string is refused rather than waved through: `content` is an
            // untyped JSON column, so an array or an object here is a shape the editor never wrote,
            // and an allowlist that trusts what it cannot read is not one.
            if (!is_string($uri) || preg_match(self::SAFE_URI_PATTERN, trim($uri)) !== 1) {
                return false;
            }
        }

        return true;
    }
}
