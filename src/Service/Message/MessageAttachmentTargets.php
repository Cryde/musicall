<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\Task;
use App\Enum\BandSpace\BandSpaceSearchResultType;

/**
 * What a MessageAttachment.target_id points at, per target type.
 *
 * Mirrors BandSpaceFileSourceTypes, for the same reason: the column is polymorphic and carries no
 * foreign key, so these maps are the only thing that can tell the resolver which table to look a
 * target up in and which of its columns is the title.
 *
 * Keyed by BandSpaceSearchResultType value rather than by a list of its own, and
 * MessageAttachmentTargetsTest fails if either map stops covering the enum: an eighth kind of record
 * must not be silently unresolvable.
 */
final class MessageAttachmentTargets
{
    /**
     * @var array<string, class-string>
     */
    public const array ENTITY_BY_TYPE = [
        BandSpaceSearchResultType::Agenda->value => AgendaEntry::class,
        BandSpaceSearchResultType::Task->value => Task::class,
        BandSpaceSearchResultType::Note->value => BandSpaceNote::class,
        BandSpaceSearchResultType::File->value => BandSpaceFile::class,
        BandSpaceSearchResultType::Setlist->value => Setlist::class,
        BandSpaceSearchResultType::Song->value => Song::class,
        BandSpaceSearchResultType::Finance->value => FinanceEntry::class,
    ];

    /**
     * The property a card shows, which is also the one snapshotted onto the attachment row. Same
     * notion of a title as BandSpaceSearchResultBuilder projects, so a card and a command palette hit
     * cannot end up naming the same record differently.
     *
     * @var array<string, string>
     */
    public const array TITLE_PROPERTY_BY_TYPE = [
        BandSpaceSearchResultType::Agenda->value => 'title',
        BandSpaceSearchResultType::Task->value => 'title',
        BandSpaceSearchResultType::Note->value => 'title',
        BandSpaceSearchResultType::File->value => 'originalName',
        BandSpaceSearchResultType::Setlist->value => 'name',
        BandSpaceSearchResultType::Song->value => 'title',
        BandSpaceSearchResultType::Finance->value => 'label',
    ];
}
