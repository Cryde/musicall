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
     * The cards of a page of messages, keyed by message id.
     *
     * A card is always named by the label snapshotted when it was attached, never by the target's
     * title read back now. That is what lets a message stay readable after the task it named is
     * deleted, and it is also what keeps a reader from being shown a value the target's owner
     * disclosed once: a personal finance entry is walled to the member it names everywhere else, and
     * #785 would put a wall around more of them. Every reader therefore sees the same card.
     *
     * `is_available` is the only thing read live, and it answers one question: is the row still
     * there. False means the client renders the label with a « (supprimé) » suffix and no link.
     *
     * @param string[] $messageIds
     *
     * @return array<string, list<array{type: string, target_id: string, label: string, is_available: bool}>>
     */
    public function resolveForMessages(array $messageIds, string $bandSpaceId): array
    {
        $rowsByMessage = $this->messageAttachmentRepository->findByMessageIds($messageIds);
        if ($rowsByMessage === []) {
            return [];
        }

        $idsByType = [];
        foreach ($rowsByMessage as $rows) {
            foreach ($rows as $row) {
                $idsByType[$row['type']->value][] = $row['targetId'];
            }
        }

        // One query per kind of target present, and none at all when there is nothing to resolve.
        $existingByType = [];
        foreach ($idsByType as $typeValue => $targetIds) {
            $existingByType[$typeValue] = $this->messageAttachmentRepository->findExistingTargetIds(
                BandSpaceSearchResultType::from($typeValue),
                array_values(array_unique($targetIds)),
                $bandSpaceId,
            );
        }

        $cardsByMessage = [];
        foreach ($rowsByMessage as $messageId => $rows) {
            foreach ($rows as $row) {
                $cardsByMessage[$messageId][] = [
                    'type' => $row['type']->value,
                    'target_id' => $row['targetId'],
                    'label' => $row['label'],
                    'is_available' => isset($existingByType[$row['type']->value][mb_strtolower($row['targetId'])]),
                ];
            }
        }

        return $cardsByMessage;
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
