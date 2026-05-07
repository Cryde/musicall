<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\AgendaEntryCreateProcessor;
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
class AgendaEntryCreate
{
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
}
