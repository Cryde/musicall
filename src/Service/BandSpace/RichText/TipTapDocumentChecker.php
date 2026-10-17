<?php declare(strict_types=1);

namespace App\Service\BandSpace\RichText;

use App\Enum\BandSpace\RichTextSurface;
use App\Enum\BandSpace\TechRiderColour;

/**
 * Whether a document is in the format TipTapVocabulary describes (#1012). The web can only produce
 * that format, but the mobile app writes the same field through its own converter, so the API is
 * where the format becomes a contract rather than a convention.
 *
 * Link and image URIs are deliberately not checked: the editor saves a pasted external image or a
 * `tel:` link, so refusing them would stop a member's autosave. They are filtered on the way out
 * instead, by BandSpaceNoteBuilder and TipTapHtmlRenderer. Nesting and attribute types are not
 * checked either, ProseMirror loads a document whatever they are, except where a wrong type would
 * change what the member reads: a heading's level and a checklist item's `checked`.
 *
 * Walked with a stack rather than recursively: the depth is the client's to choose.
 */
readonly class TipTapDocumentChecker
{
    private const string NOT_A_DOCUMENT = 'band_space.rich_text.not_a_document';
    private const string NODE_WITHOUT_TYPE = 'band_space.rich_text.node_without_type';
    private const string UNKNOWN_NODE = 'band_space.rich_text.unknown_node';
    private const string UNKNOWN_MARK = 'band_space.rich_text.unknown_mark';
    private const string HEADING_LEVEL = 'band_space.rich_text.heading_level';
    private const string HEADING_LEVELS = 'band_space.rich_text.heading_levels';
    private const string CHECKBOX = 'band_space.rich_text.checkbox_invalid';
    private const string ALIGNMENT = 'band_space.rich_text.alignment_unsupported';
    private const string COLOUR = 'band_space.rich_text.colour_off_palette';

    /** Long enough to recognise a node type in a message, short enough not to echo a payload. */
    private const int QUOTED_TYPE_LENGTH = 40;

    /**
     * The first reason the document is out of the format, as a message for the member, or null.
     *
     * @param array<array-key, mixed> $document
     */
    public function findProblem(array $document, RichTextSurface $surface): ?TipTapDocumentProblem
    {
        if (($document['type'] ?? null) !== 'doc') {
            return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
        }

        $allowedNodes = $surface->nodes();
        $allowedMarks = $surface->marks();
        $headingLevels = $surface->headingLevels();

        $pendingLists = [$document['content'] ?? []];
        while ($pendingLists !== []) {
            $nodes = array_pop($pendingLists);
            if (!is_array($nodes) || !array_is_list($nodes)) {
                return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
            }

            foreach ($nodes as $node) {
                $problem = $this->findNodeProblem($node, $allowedNodes, $allowedMarks, $headingLevels);
                if ($problem instanceof TipTapDocumentProblem) {
                    return $problem;
                }
                if (isset($node['content'])) {
                    $pendingLists[] = $node['content'];
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $allowedNodes
     * @param list<string> $allowedMarks
     * @param list<int>    $headingLevels
     */
    private function findNodeProblem(mixed $node, array $allowedNodes, array $allowedMarks, array $headingLevels): ?TipTapDocumentProblem
    {
        if (!is_array($node)) {
            return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
        }

        $type = $node['type'] ?? null;
        if ($type === null) {
            return new TipTapDocumentProblem(self::NODE_WITHOUT_TYPE);
        }
        if (!is_string($type)) {
            return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
        }
        if (!in_array($type, $allowedNodes, true)) {
            return new TipTapDocumentProblem(self::UNKNOWN_NODE, ['{{ type }}' => mb_substr($type, 0, self::QUOTED_TYPE_LENGTH)]);
        }

        $attrs = $node['attrs'] ?? [];
        if (!is_array($attrs)) {
            return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
        }

        $problem = match (true) {
            // ProseMirror refuses an empty text node, and TipTap then loads the whole note as empty.
            $type === 'text' && (!is_string($node['text'] ?? null) || $node['text'] === '') => new TipTapDocumentProblem(self::NOT_A_DOCUMENT),
            $type === 'heading' && !in_array($attrs['level'] ?? null, $headingLevels, true) => $this->headingLevelProblem($headingLevels),
            // TipTap writes `checked` on every item; a missing one defaults to unticked.
            $type === 'taskItem' && array_key_exists('checked', $attrs) && !is_bool($attrs['checked']) => new TipTapDocumentProblem(self::CHECKBOX),
            default => $this->findAlignmentProblem($type, $attrs['textAlign'] ?? null),
        };

        return $problem ?? $this->findMarksProblem($node['marks'] ?? [], $allowedMarks);
    }

    /** @param list<int> $levels « 1, 2 ou 3 », where the « ou » belongs to the message */
    private function headingLevelProblem(array $levels): TipTapDocumentProblem
    {
        $last = (string) array_pop($levels);

        return $levels === []
            ? new TipTapDocumentProblem(self::HEADING_LEVEL, ['{{ level }}' => $last])
            : new TipTapDocumentProblem(self::HEADING_LEVELS, ['{{ levels }}' => implode(', ', $levels), '{{ last }}' => $last]);
    }

    /** The editor writes `textAlign: null` on every paragraph and heading it has not aligned. */
    private function findAlignmentProblem(string $type, mixed $alignment): ?TipTapDocumentProblem
    {
        if ($alignment === null) {
            return null;
        }

        return in_array($type, TipTapVocabulary::ALIGNABLE_NODES, true) && in_array($alignment, TipTapVocabulary::TEXT_ALIGNMENTS, true)
            ? null
            : new TipTapDocumentProblem(self::ALIGNMENT);
    }

    /** @param list<string> $allowedMarks */
    private function findMarksProblem(mixed $marks, array $allowedMarks): ?TipTapDocumentProblem
    {
        if (!is_array($marks) || !array_is_list($marks)) {
            return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
        }

        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;
            if (!is_string($type)) {
                return new TipTapDocumentProblem(self::NOT_A_DOCUMENT);
            }
            if (!in_array($type, $allowedMarks, true)) {
                return new TipTapDocumentProblem(self::UNKNOWN_MARK, ['{{ type }}' => mb_substr($type, 0, self::QUOTED_TYPE_LENGTH)]);
            }

            $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
            if ($type === TipTapVocabulary::COLOUR_MARK && !$this->isPaletteColour($attrs['color'] ?? null)) {
                return new TipTapDocumentProblem(self::COLOUR);
            }
        }

        return null;
    }

    /** Empty is what the editor writes for a pasted span styled with anything but a colour. */
    private function isPaletteColour(mixed $colour): bool
    {
        return $colour === null || $colour === '' || (is_string($colour) && TechRiderColour::fromHex($colour) instanceof TechRiderColour);
    }
}
