<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceSearchResult;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\Task;
use App\Enum\BandSpace\BandSpaceSearchResultType;

readonly class BandSpaceSearchResultBuilder
{
    /**
     * Numeric and locale free on purpose: the palette shows this next to a title, and a month name
     * would be the only piece of the payload needing translation.
     */
    private const string DATE_FORMAT = 'd/m/Y';

    public function buildFromAgendaEntry(AgendaEntry $entry): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::Agenda,
            resourceId: (string) $entry->id,
            bandSpaceId: (string) $entry->bandSpace->id,
            title: $entry->title,
            subtitle: $entry->eventDatetime->format(self::DATE_FORMAT),
        );
    }

    public function buildFromTask(Task $task): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::Task,
            resourceId: (string) $task->id,
            bandSpaceId: (string) $task->bandSpace->id,
            title: $task->title,
            subtitle: $task->category?->name,
        );
    }

    public function buildFromNote(BandSpaceNote $note): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::Note,
            resourceId: (string) $note->id,
            bandSpaceId: (string) $note->bandSpace->id,
            title: $note->title,
            subtitle: $note->parent?->title,
        );
    }

    public function buildFromFile(BandSpaceFile $file): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::File,
            resourceId: (string) $file->id,
            bandSpaceId: (string) $file->bandSpace->id,
            title: $file->originalName,
            subtitle: $file->folder?->name,
        );
    }

    public function buildFromSetlist(Setlist $setlist): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::Setlist,
            resourceId: (string) $setlist->id,
            bandSpaceId: (string) $setlist->bandSpace->id,
            title: $setlist->name,
            subtitle: null,
        );
    }

    public function buildFromSong(Song $song): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::Song,
            resourceId: (string) $song->id,
            bandSpaceId: (string) $song->bandSpace->id,
            title: $song->title,
            subtitle: $song->tonality,
        );
    }

    public function buildFromFinanceEntry(FinanceEntry $entry): BandSpaceSearchResult
    {
        return $this->build(
            type: BandSpaceSearchResultType::Finance,
            resourceId: (string) $entry->id,
            bandSpaceId: (string) $entry->category->bandSpace->id,
            title: $entry->label,
            subtitle: $entry->date->format(self::DATE_FORMAT),
        );
    }

    private function build(
        BandSpaceSearchResultType $type,
        string $resourceId,
        string $bandSpaceId,
        string $title,
        ?string $subtitle,
    ): BandSpaceSearchResult {
        $dto = new BandSpaceSearchResult();
        $dto->id = $type->value . '-' . $resourceId;
        $dto->bandSpaceId = $bandSpaceId;
        $dto->type = $type->value;
        $dto->resourceId = $resourceId;
        $dto->title = $title;
        $dto->subtitle = $subtitle;

        return $dto;
    }
}
