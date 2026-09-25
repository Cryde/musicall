<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\Message\MessageAttachmentRepository;
use Ramsey\Uuid\Uuid;

/**
 * Turns attachment rows into the cards a chat message carries, and the synthetic identifiers a
 * client sends into the targets they name (#970).
 *
 * Everything here works on a whole set at once. A page of fifty messages costs one query for the
 * rows plus one per kind of target actually present, never one per message and never one per
 * attachment: resolving per message is the single mistake that would make a chat scroll expensive,
 * and the projection ChatMessageCollectionProvider reads exists precisely to avoid that class of
 * cost (#730).
 */
readonly class MessageAttachmentResolver
{
    public function __construct(
        private MessageAttachmentRepository $messageAttachmentRepository,
    ) {
    }

    /**
     * The cards of a page of messages, keyed by message id, as `$viewer` may see them.
     *
     * A card follows its target (#1048): it reads the target's current title whenever this reader can
     * see the target, since the card is a link to the item rather than a quote from it. The label
     * snapshotted when it was attached is the fallback, and it does two jobs: it keeps a card readable
     * once the target is deleted, and it is all a reader is shown of a target they cannot see, such as
     * another member's personal finance entry. That entry's later renames therefore never reach them,
     * which is the leak #970 closed. Which reader may see what is decided in one place,
     * MessageAttachmentRepository::findTargetTitles(), where #785 would add its own walls.
     *
     * `is_available` stays reader independent: it answers whether the target still exists, not whether
     * this reader can open it. False means the client renders the label with « (supprimé) » and no link.
     *
     * One query per kind of target present for the titles, plus one more only for the kinds where some
     * target came back without one, to tell a deleted target from a hidden one. Never one per message.
     *
     * @param string[] $messageIds
     *
     * @return array<string, list<array{type: string, target_id: string, label: string, is_available: bool}>>
     */
    public function resolveForMessages(array $messageIds, string $bandSpaceId, BandSpaceMembership $viewer): array
    {
        $rowsByMessage = $this->messageAttachmentRepository->findByMessageIds($messageIds);
        if ($rowsByMessage === []) {
            return [];
        }

        $idsByType = [];
        foreach ($rowsByMessage as $rows) {
            foreach ($rows as $row) {
                $idsByType[$row['type']->value][] = mb_strtolower($row['targetId']);
            }
        }

        $titlesByType = $this->titlesByType($idsByType, $bandSpaceId, $viewer);
        $existingByType = $this->untitledTargetsThatExist($idsByType, $titlesByType, $bandSpaceId);

        $cardsByMessage = [];
        foreach ($rowsByMessage as $messageId => $rows) {
            foreach ($rows as $row) {
                $type = $row['type']->value;
                $targetId = mb_strtolower($row['targetId']);
                $title = $titlesByType[$type][$targetId] ?? null;

                $cardsByMessage[$messageId][] = [
                    'type' => $type,
                    'target_id' => $row['targetId'],
                    'label' => $title ?? $row['label'],
                    'is_available' => $title !== null || isset($existingByType[$type][$targetId]),
                ];
            }
        }

        return $cardsByMessage;
    }

    /**
     * Of the targets the title lookup did not return, the ones that still exist: hidden from this
     * reader rather than deleted. Asked only for the kinds that have such targets, which on a normal
     * page is none.
     *
     * @param array<string, string[]> $idsByType
     * @param array<string, array<string, string>> $titlesByType
     *
     * @return array<string, array<string, true>> type value => (lower-cased target id => true)
     */
    private function untitledTargetsThatExist(array $idsByType, array $titlesByType, string $bandSpaceId): array
    {
        $existingByType = [];
        foreach ($idsByType as $typeValue => $targetIds) {
            $untitled = array_values(array_unique(array_filter(
                $targetIds,
                static fn (string $targetId): bool => !isset($titlesByType[$typeValue][$targetId]),
            )));
            if ($untitled === []) {
                continue;
            }

            $existingByType[$typeValue] = $this->messageAttachmentRepository->findExistingTargetIds(
                BandSpaceSearchResultType::from($typeValue),
                $untitled,
                $bandSpaceId,
            );
        }

        return $existingByType;
    }

    /**
     * The targets a client named with the synthetic `<type>-<uuid>` identifiers BandSpaceSearchResult
     * hands it, in the order they were sent.
     *
     * An entry is null when the identifier is unusable or names nothing this member can reach in this
     * space, which is what both the validator and the write path read as "refuse this one". Resolving
     * here rather than trusting the client is what stops a pasted URL (#972) reaching into a space the
     * sender is not a member of.
     *
     * @param mixed[] $identifiers
     *
     * @return array<int, array{type: BandSpaceSearchResultType, targetId: string, label: string}|null>
     */
    public function resolveIdentifiers(array $identifiers, string $bandSpaceId, BandSpaceMembership $viewer): array
    {
        $parsed = array_map($this->parseIdentifier(...), array_values($identifiers));

        $idsByType = [];
        foreach ($parsed as $target) {
            if ($target !== null) {
                $idsByType[$target[0]->value][] = $target[1];
            }
        }

        $titlesByType = $this->titlesByType($idsByType, $bandSpaceId, $viewer);

        return array_map(
            static function (?array $target) use ($titlesByType): ?array {
                if ($target === null) {
                    return null;
                }

                [$type, $targetId] = $target;
                $title = $titlesByType[$type->value][$targetId] ?? null;

                return $title === null
                    ? null
                    : ['type' => $type, 'targetId' => $targetId, 'label' => $title];
            },
            $parsed,
        );
    }

    /**
     * One query per kind of target present, and none at all when there is nothing to resolve.
     *
     * @param array<string, string[]> $idsByType
     *
     * @return array<string, array<string, string>> type value => (lower-cased target id => title)
     */
    private function titlesByType(array $idsByType, string $bandSpaceId, BandSpaceMembership $viewer): array
    {
        $titlesByType = [];
        foreach ($idsByType as $typeValue => $targetIds) {
            $titlesByType[$typeValue] = $this->messageAttachmentRepository->findTargetTitles(
                BandSpaceSearchResultType::from($typeValue),
                array_values(array_unique($targetIds)),
                $bandSpaceId,
                $viewer,
            );
        }

        return $titlesByType;
    }

    /**
     * `<type>-<uuid>`, split on the first hyphen because a uuid is full of them and no type value has
     * one. The uuid is checked here rather than left to the query: handing a malformed one to a uuid
     * column throws out of Doctrine's type conversion, which would answer a typo with a 500.
     *
     * @return array{BandSpaceSearchResultType, string}|null
     */
    private function parseIdentifier(mixed $identifier): ?array
    {
        if (!is_string($identifier)) {
            return null;
        }

        $parts = explode('-', $identifier, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $type = BandSpaceSearchResultType::tryFrom($parts[0]);
        if ($type === null || !Uuid::isValid($parts[1])) {
            return null;
        }

        return [$type, mb_strtolower($parts[1])];
    }
}
