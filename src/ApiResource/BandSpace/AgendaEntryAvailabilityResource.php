<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\AgendaEntryAvailabilityAnswerProcessor;
use App\State\Processor\BandSpace\AgendaEntryAvailabilityRemindProcessor;
use App\State\Provider\BandSpace\AgendaEntryAvailabilityProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Who can make one date of an agenda entry (#1000): every active member with their answer, and the
 * totals the home card shows as « 3 dispos · 1 sans réponse ».
 *
 * `occurrence` is the UTC date of the occurrence's start, the `occurrence_date` every agenda item
 * carries. It may be left out for a one-off entry, which has a single date.
 */
#[ApiResource(
    shortName: 'AgendaEntryAvailability',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{entryId}/availability',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'entryId' => new Link(fromClass: self::class, identifiers: ['entryId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_entry_availability_get',
            provider: AgendaEntryAvailabilityProvider::class,
            parameters: [
                'occurrence' => new QueryParameter(key: 'occurrence', constraints: [new Assert\Date()]),
            ],
        ),
        // The caller's own answer. Sending it again replaces it.
        new Put(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{entryId}/availability',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'entryId' => new Link(fromClass: self::class, identifiers: ['entryId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            input: AgendaEntryAvailabilityAnswer::class,
            read: false,
            name: 'api_band_space_agenda_entry_availability_put',
            processor: AgendaEntryAvailabilityAnswerProcessor::class,
        ),
        // « Relancer »: a bell and a push to the members who have neither answered nor declared an absence.
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-entries/{entryId}/availability/remind',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'entryId' => new Link(fromClass: self::class, identifiers: ['entryId']),
            ],
            status: 204,
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            input: AgendaEntryAvailabilityReminder::class,
            output: false,
            read: false,
            name: 'api_band_space_agenda_entry_availability_remind',
            processor: AgendaEntryAvailabilityRemindProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class AgendaEntryAvailabilityResource
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $entryId;

    public string $occurrenceDate;

    /** False once the date has passed: the record of who came stays as it was. */
    public bool $canAnswer;

    /** The entry's author and the admins, while the date is still ahead. */
    public bool $canRemind;

    /**
     * The caller's state, as in `members`. `absent` is derived from a declared absence, not an
     * answer they gave, so a client should still offer them the two buttons.
     */
    public ?string $myAnswer = null;

    /** @var array{yes: int, no: int, absent: int, pending: int} */
    public array $totals;

    /**
     * Each active member, `answer` being `yes`, `no`, `absent` (a declared absence and no answer) or
     * null for no answer yet.
     *
     * @var list<array{membership_id: string, user_id: string, display_name: string, profile_picture_url: string|null, answer: string|null, answered_at: string|null}>
     */
    public array $members = [];
}
