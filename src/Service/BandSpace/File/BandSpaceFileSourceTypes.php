<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\Task;

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

    /**
     * The entity a sourceId points at, per source type. There is no foreign key on
     * band_space_file_attachment.source_id (the column is polymorphic), so this map is the only thing
     * that can tell app:band-space:prune-orphan-attachments which table to look the source up in.
     *
     * Must list exactly the same keys as ALL; the attribute-argument rules make it impossible to derive
     * one from the other with array_keys().
     *
     * @var array<string, class-string>
     */
    public const array ENTITY_BY_TYPE = [
        'task' => Task::class,
        'finance' => FinanceEntry::class,
        'note' => BandSpaceNote::class,
        'song' => Song::class,
        'setlist' => Setlist::class,
    ];
}
