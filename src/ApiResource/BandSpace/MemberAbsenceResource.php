<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\MemberAbsenceDeleteProcessor;
use App\State\Processor\BandSpace\MemberAbsenceUpdateProcessor;
use App\State\Provider\BandSpace\MemberAbsenceCollectionProvider;
use App\State\Provider\BandSpace\MemberAbsenceItemProvider;
use App\Validator\BandSpace\Agenda\ValidAbsenceRange;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MemberAbsence',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/absences',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_absences_get_collection',
            provider: MemberAbsenceCollectionProvider::class,
            parameters: [
                'from' => new QueryParameter(key: 'from'),
                'to' => new QueryParameter(key: 'to'),
            ],
        ),
        // Nothing on the front end fetches one absence on its own, but the IRI every payload
        // advertises has to resolve: without an item Get, API Platform falls back to a generic
        // /member_absences/id=...;bandSpaceId=... that answers nothing.
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/absences/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_absences_get_item',
            provider: MemberAbsenceItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/absences/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            collectDenormalizationErrors: true,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_absences_patch',
            provider: MemberAbsenceItemProvider::class,
            processor: MemberAbsenceUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/absences/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_absences_delete',
            provider: MemberAbsenceItemProvider::class,
            processor: MemberAbsenceDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
#[ValidAbsenceRange]
class MemberAbsenceResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    /** The membership the absence belongs to. A PATCH never moves it: that is a delete plus a create. */
    public string $memberId;

    /** The member's stage name when they have one, their username otherwise. */
    public string $displayName;

    public ?string $profilePictureUrl = null;

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

    /**
     * Whether the reader may edit or delete this absence: their own, or anyone's when they are an
     * admin. Resolved server side because the client cannot work it out - the security store carries
     * a username and roles, and the band space payload a role, but never the membership id.
     */
    public bool $canManage = false;

    public \DateTimeInterface $creationDatetime;
}
