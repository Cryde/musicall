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
    private const string NOT_A_DOCUMENT = 'Le contenu n\'est pas un document valide';
    private const string NODE_WITHOUT_TYPE = 'Le contenu contient un élément sans type';
    private const string UNKNOWN_NODE = 'Le contenu contient un élément non pris en charge (« %s »)';
    private const string UNKNOWN_MARK = 'Le contenu contient une mise en forme non prise en charge (« %s »)';
    private const string HEADING_LEVEL = 'Les titres doivent être de niveau %s';
    private const string CHECKBOX = 'Le contenu contient une case à cocher invalide';
    private const string ALIGNMENT = 'Le contenu contient un alignement non pris en charge';
    private const string COLOUR = 'Le contenu contient une couleur hors de la palette';

    /** Long enough to recognise a node type in a message, short enough not to echo a payload. */
    private const int QUOTED_TYPE_LENGTH = 40;

    /**
     * The first reason the document is out of the format, as a message for the member, or null.
     *
     * @param array<array-key, mixed> $document
     */
    public function findProblem(array $document, RichTextSurface $surface): ?string
    {
        if (($document['type'] ?? null) !== 'doc') {
            return self::NOT_A_DOCUMENT;
        }

        $allowedNodes = $surface->nodes();
        $allowedMarks = $surface->marks();
        $headingLevels = $surface->headingLevels();

        $pendingLists = [$document['content'] ?? []];
        while ($pendingLists !== []) {
            $nodes = array_pop($pendingLists);
            if (!is_array($nodes) || !array_is_list($nodes)) {
                return self::NOT_A_DOCUMENT;
            }

            foreach ($nodes as $node) {
                $problem = $this->findNodeProblem($node, $allowedNodes, $allowedMarks, $headingLevels);
                if ($problem !== null) {
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
    private function findNodeProblem(mixed $node, array $allowedNodes, array $allowedMarks, array $headingLevels): ?string
    {
        if (!is_array($node)) {
            return self::NOT_A_DOCUMENT;
        }

        $type = $node['type'] ?? null;
        if ($type === null) {
            return self::NODE_WITHOUT_TYPE;
        }
        if (!is_string($type)) {
            return self::NOT_A_DOCUMENT;
        }
        if (!in_array($type, $allowedNodes, true)) {
            return sprintf(self::UNKNOWN_NODE, mb_substr($type, 0, self::QUOTED_TYPE_LENGTH));
        }

        $attrs = $node['attrs'] ?? [];
        if (!is_array($attrs)) {
            return self::NOT_A_DOCUMENT;
        }

        $problem = match (true) {
            // ProseMirror refuses an empty text node, and TipTap then loads the whole note as empty.
            $type === 'text' && (!is_string($node['text'] ?? null) || $node['text'] === '') => self::NOT_A_DOCUMENT,
            $type === 'heading' && !in_array($attrs['level'] ?? null, $headingLevels, true) => $this->headingLevelMessage($headingLevels),
            // TipTap writes `checked` on every item; a missing one defaults to unticked.
            $type === 'taskItem' && array_key_exists('checked', $attrs) && !is_bool($attrs['checked']) => self::CHECKBOX,
            default => $this->findAlignmentProblem($type, $attrs['textAlign'] ?? null),
        };

        return $problem ?? $this->findMarksProblem($node['marks'] ?? [], $allowedMarks);
    }

    /** @param list<int> $levels « 1, 2 ou 3 » */
    private function headingLevelMessage(array $levels): string
    {
        $last = array_pop($levels);

        return sprintf(self::HEADING_LEVEL, $levels === [] ? (string) $last : implode(', ', $levels) . ' ou ' . $last);
    }

    /** The editor writes `textAlign: null` on every paragraph and heading it has not aligned. */
    private function findAlignmentProblem(string $type, mixed $alignment): ?string
    {
        if ($alignment === null) {
            return null;
        }

        return in_array($type, TipTapVocabulary::ALIGNABLE_NODES, true) && in_array($alignment, TipTapVocabulary::TEXT_ALIGNMENTS, true)
            ? null
            : self::ALIGNMENT;
    }

    /** @param list<string> $allowedMarks */
    private function findMarksProblem(mixed $marks, array $allowedMarks): ?string
    {
        if (!is_array($marks) || !array_is_list($marks)) {
            return self::NOT_A_DOCUMENT;
        }

        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;
            if (!is_string($type)) {
                return self::NOT_A_DOCUMENT;
            }
            if (!in_array($type, $allowedMarks, true)) {
                return sprintf(self::UNKNOWN_MARK, mb_substr($type, 0, self::QUOTED_TYPE_LENGTH));
            }

            $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
            if ($type === TipTapVocabulary::COLOUR_MARK && !$this->isPaletteColour($attrs['color'] ?? null)) {
                return self::COLOUR;
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
