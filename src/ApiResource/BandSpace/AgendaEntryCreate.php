<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\AgendaEntryCreateProcessor;
use App\Validator\BandSpace\Agenda\ValidRecurrence;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: AgendaEntryResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Agenda']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: AgendaEntryResource::class,
    name: 'api_band_space_agenda_entries_post',
    processor: AgendaEntryCreateProcessor::class,
)]
#[ValidRecurrence]
class AgendaEntryCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    public ?string $description = null;

    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères')]
    public ?string $location = null;

    /**
     * ISO-8601. Any offset is converted to UTC before it is stored, so `2026-08-25T20:00:00+02:00` and
     * `2026-08-25T18:00:00Z` name the same instant and both read back as `2026-08-25T18:00:00+00:00`.
     *
     * The exception is an all day entry, which is a date rather than an instant: there the offset is
     * dropped rather than applied, so the day is the one the caller wrote.
     */
    #[Assert\NotBlank(message: 'Veuillez spécifier une date et heure')]
    public \DateTimeImmutable $eventDatetime;

    #[Assert\GreaterThan(propertyPath: 'eventDatetime', message: 'La fin doit être postérieure au début')]
    public ?\DateTimeImmutable $endDatetime = null;

    public bool $isAllDay = false;

    public ?string $recurrenceFrequency = null;

    public ?string $recurrenceUntilDate = null;

    public ?string $recurrenceMonthlyMode = null;
}
