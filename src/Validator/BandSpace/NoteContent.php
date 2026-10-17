<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

/**
 * Bounds a note's body and holds it to the rich text format (#1012).
 */
#[\Attribute]
class NoteContent extends Constraint
{
    public const string ERROR_CODE = 'music_all_5d0e8b47-1c6a-4f2e-9a83-7b4c2e9f1d60';

    /** A note is the module's long form document, so it gets more room than a rider item. */
    public const int MAX_CONTENT_BYTES = 500_000;

    /** In Ko rather than characters: the limit is measured on the encoded JSON, in bytes. */
    public string $tooLargeMessage = 'band_space.rich_text.note_too_large';
}
