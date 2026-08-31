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

    /**
     * Whether the reader may edit or delete this absence: their own, or anyone's when they are an
     * admin. Resolved server side because the client cannot work it out - the security store carries
     * a username and roles, and the band space payload a role, but never the membership id.
     */
    public bool $canManage = false;

    public \DateTimeInterface $creationDatetime;
}
