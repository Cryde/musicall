<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\AgendaEntryCreateProcessor;
use App\Validator\BandSpace\Agenda\ValidRecurrence;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
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
    // Line comments, not a docblock: a doc comment here would become the *last* one before the
    // property, so PHP would attach it instead of the contract above and API Platform would publish
    // it as the OpenAPI description on a docs endpoint anyone can read.
    //
    // Explicitly the loose parser, which is what an agenda entry means: an instant carries an offset
    // and matches RFC3339, but an all day entry is a bare `Y-m-d` and the browser's toISOString()
    // adds milliseconds, so neither matches DateTimeNormalizer's default format. Without this the
    // loose fallback is taken anyway and Symfony 8.1 deprecates it, which becomes a throw in 9.0.
    #[Context(denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => null])]
    public \DateTimeImmutable $eventDatetime;

    #[Assert\GreaterThan(propertyPath: 'eventDatetime', message: 'La fin doit être postérieure au début')]
    #[Context(denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => null])]
    public ?\DateTimeImmutable $endDatetime = null;

    public bool $isAllDay = false;

    public ?string $recurrenceFrequency = null;

    /**
     * The last day the series may produce an occurrence, `Y-m-d`. No NotBlank: whether it is required
     * at all depends on recurrenceFrequency, which is ValidRecurrence's call.
     */
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public ?string $recurrenceUntilDate = null;

    public ?string $recurrenceMonthlyMode = null;
}
