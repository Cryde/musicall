<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\AgendaEntryDeleteProcessor;
use App\State\Processor\BandSpace\AgendaEntryFromOccurrenceDeleteProcessor;
use App\State\Processor\BandSpace\AgendaEntryOccurrenceDeleteProcessor;
use App\State\Processor\BandSpace\AgendaEntryUpdateProcessor;
use App\State\Provider\BandSpace\AgendaEntryCollectionProvider;
use App\State\Provider\BandSpace\AgendaEntryItemProvider;
use App\Validator\BandSpace\Agenda\ValidRecurrence;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AgendaEntry',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entries_get_collection',
            provider: AgendaEntryCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entries_get_item',
            provider: AgendaEntryItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entries_patch',
            provider: AgendaEntryItemProvider::class,
            processor: AgendaEntryUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entries_delete',
            provider: AgendaEntryItemProvider::class,
            processor: AgendaEntryDeleteProcessor::class,
        ),
        // Cancel a single occurrence of a recurring entry (creates an exception row).
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{id}/occurrences/{occurrenceDate}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entries_delete_occurrence',
            provider: AgendaEntryItemProvider::class,
            processor: AgendaEntryOccurrenceDeleteProcessor::class,
        ),
        // Truncate the recurring series at the day before the picked occurrence.
        // If the picked date is on or before the first occurrence, deletes the entry outright.
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{id}/from/{occurrenceDate}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entries_delete_from_occurrence',
            provider: AgendaEntryItemProvider::class,
            processor: AgendaEntryFromOccurrenceDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
#[ValidRecurrence]
class AgendaEntryResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    public ?string $description = null;

    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères')]
    public ?string $location = null;

    #[Assert\NotBlank(message: 'Veuillez spécifier une date et heure')]
    public string $eventDatetime;

    #[Assert\GreaterThan(propertyPath: 'eventDatetime', message: 'La fin doit être postérieure au début')]
    public ?string $endDatetime = null;

    public bool $isAllDay = false;

    public ?string $recurrenceFrequency = null;

    public ?string $recurrenceUntilDate = null;

    public ?string $recurrenceMonthlyMode = null;

    public ?string $creatorId = null;
    public ?string $creatorUsername = null;
    public string $creationDatetime;
}
