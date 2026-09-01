<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\MemberAbsenceCreateProcessor;
use App\Validator\BandSpace\Agenda\ValidAbsenceRange;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/absences',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: MemberAbsenceResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Agenda']),
    normalizationContext: ['skip_null_values' => false],
    collectDenormalizationErrors: true,
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

    /** A calendar date, `Y-m-d` on the wire in both directions. */
    #[Assert\NotNull(message: 'Veuillez spécifier une date de début')]
    // Out as a bare `Y-m-d`: an absence is a calendar date, and an ATOM instant would have to be
    // unpinned by every reader, which is the bug class assets/js/utils/agendaDate.js documents.
    //
    // In through the loose parser on purpose, NOT a strict `!Y-m-d`. createFromFormat raises a
    // ValueError on a null byte, and DateTimeNormalizer only catches Exception, so a strict format
    // turns `2026-08-10\0` into a 500. The constructor parses the same input and tolerates that byte.
    // The offset a loose parse may carry is neutralised by the processor, which keeps the caller's
    // own written day.
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public ?\DateTimeImmutable $startDate = null;

    /** Inclusive: the member is away for the whole of this day too. */
    #[Assert\NotNull(message: 'Veuillez spécifier une date de fin')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'La date de fin doit être postérieure ou égale à la date de début.')]
    // Out as a bare `Y-m-d`: an absence is a calendar date, and an ATOM instant would have to be
    // unpinned by every reader, which is the bug class assets/js/utils/agendaDate.js documents.
    //
    // In through the loose parser on purpose, NOT a strict `!Y-m-d`. createFromFormat raises a
    // ValueError on a null byte, and DateTimeNormalizer only catches Exception, so a strict format
    // turns `2026-08-10\0` into a 500. The constructor parses the same input and tolerates that byte.
    // The offset a loose parse may carry is neutralised by the processor, which keeps the caller's
    // own written day.
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public ?\DateTimeImmutable $endDate = null;

    #[Assert\Length(max: 120, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères')]
    public ?string $reason = null;
}
