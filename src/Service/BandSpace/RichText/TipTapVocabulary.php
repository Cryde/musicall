<?php declare(strict_types=1);

namespace App\Service\BandSpace\RichText;

/**
 * The rich text format notes and tech rider texts are stored in, read off the editor setup in
 * `assets/js/composables/useRichTextEditor.js` and the installed TipTap packages (#1012).
 *
 * StarterKit 3 bundles link and underline although the editor's extension list does not mention
 * them. Headings are limited to h2 and h3, and table rows hold only cells, so `tableHeader` is not
 * part of the format even though it is standard TipTap.
 */
final class TipTapVocabulary
{
    /** Every node a document can hold besides `doc` and `text`, before the per-surface extras. */
    public const array NODES = [
        'paragraph',
        'heading',
        'bulletList',
        'orderedList',
        'listItem',
        'blockquote',
        'codeBlock',
        'horizontalRule',
        'hardBreak',
        'table',
        'tableRow',
        'tableCell',
    ];

    /** Notes upload images; a rider text has never been able to hold one. */
    public const string IMAGE_NODE = 'image';

    public const array MARKS = ['bold', 'italic', 'strike', 'underline', 'code', 'link'];

    /** The rider colours text from its palette, see TechRiderColour. */
    public const string COLOUR_MARK = 'textStyle';

    public const array HEADING_LEVELS = [2, 3];

    /** TextAlign is configured on these nodes only. */
    public const array ALIGNABLE_NODES = ['heading', 'paragraph'];

    public const array TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify'];
}
