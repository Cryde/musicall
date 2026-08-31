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
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
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

    /** `Y-m-d`. A calendar date and never an instant, so no offset can move the day. */
    #[Assert\NotBlank(message: 'Veuillez spécifier une date de début')]
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public string $startDate;

    /** `Y-m-d`, inclusive: the member is away for the whole of this day too. */
    #[Assert\NotBlank(message: 'Veuillez spécifier une date de fin')]
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    // Both are zero padded ISO dates, so the string comparison this makes is chronological.
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'La date de fin doit être postérieure ou égale à la date de début.')]
    public string $endDate;

    #[Assert\Length(max: 120, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères')]
    public ?string $reason = null;
}
