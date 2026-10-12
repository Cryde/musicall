<?php declare(strict_types=1);

namespace App\Service\BandSpace\RichText;

/**
 * The rich text format notes and tech rider texts are stored in, read off the editor setup in
 * `assets/js/composables/useRichTextEditor.js` and the installed TipTap packages (#1012).
 *
 * StarterKit 3 bundles link and underline although the editor's extension list does not mention
 * them. Table rows hold only cells, so `tableHeader` is not part of the format even though it is
 * standard TipTap. What each surface adds on top is in RichTextSurface.
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

    /** Notes only, as on the phone (#1175): a rider text is sent to a venue as a PDF. */
    public const array CHECKLIST_NODES = ['taskList', 'taskItem'];

    public const array MARKS = ['bold', 'italic', 'strike', 'underline', 'code', 'link'];

    /** The rider colours text from its palette, see TechRiderColour. */
    public const string COLOUR_MARK = 'textStyle';

    /** A rider text's headings, below the PDF's own section titles. */
    public const array RIDER_HEADING_LEVELS = [2, 3];

    public const array NOTE_HEADING_LEVELS = [1, 2, 3];

    /** TextAlign is configured on these nodes only. */
    public const array ALIGNABLE_NODES = ['heading', 'paragraph'];

    public const array TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify'];
}
