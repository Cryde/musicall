<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

/**
 * Single source of truth for the BandSpaceFileAttachment.sourceType allowlist.
 * Referenced by both the generic upload processor (attachedSourceType field)
 * and the attach-existing input DTO (Assert\Choice). The match() blocks in
 * BandSpaceFileAttachProcessor + BandSpaceFileBuilder enumerate the same values
 * explicitly; if you add or remove one here, update those matches too.
 */
final class BandSpaceFileSourceTypes
{
    public const array ALL = ['task', 'finance', 'note', 'song', 'setlist'];
}
