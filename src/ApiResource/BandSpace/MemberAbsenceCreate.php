<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\MemberAbsenceCreateProcessor;
use App\Validator\BandSpace\Agenda\ValidAbsenceRange;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/absences',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: MemberAbsenceResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Agenda']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    output: MemberAbsenceResource::class,
    name: 'api_band_space_absences_post',
    processor: MemberAbsenceCreateProcessor::class,
)]
#[ValidAbsenceRange]
class MemberAbsenceCreate
{
    /**
     * The membership the absence is recorded for. Null means the caller's own, which is what a member
     * always gets: only an admin may name somebody else, and the processor enforces it.
     */
    public ?string $memberId = null;

    /**
     * A calendar date, `Y-m-d` on the wire in both directions.
     *
     * A string rather than a DateTimeImmutable: `Assert\Date` is the only option that keeps the
     * `checkdate` calendar check, so `2026-02-31` is refused instead of being stored as 3 March. A
     * string also carries no offset, so nothing can move the day, which is what the processor used
     * to have to undo by hand.
     */
    #[Assert\NotBlank(message: 'band_space.absence.start_date_required')]
    #[Assert\Date(message: 'band_space.absence.date_invalid')]
    public string $startDate;

    /** Inclusive: the member is away for the whole of this day too. Ordering is ValidAbsenceRange's. */
    #[Assert\NotBlank(message: 'band_space.absence.end_date_required')]
    #[Assert\Date(message: 'band_space.absence.date_invalid')]
    public string $endDate;

    #[Assert\Length(max: 120, maxMessage: 'band_space.absence.reason_too_long')]
    public ?string $reason = null;
}
