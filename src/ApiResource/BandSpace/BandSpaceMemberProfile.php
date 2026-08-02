<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceMemberProfileUpdateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A member's stage name and instruments, separate from the role and status endpoint above it.
 *
 * Separate because the authorization is different in kind. Role and status are governance and
 * belong to admins; what somebody is called and what they play describes the person, so they
 * edit their own and an admin may edit anyone's. Folding both into one PATCH would mean one
 * handler holding two unrelated rules.
 */
#[Patch(
    uriTemplate: '/band_spaces/{bandSpaceId}/members/{id}/profile',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: BandSpaceMember::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: BandSpaceMember::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Member']),
    security: "is_granted('ROLE_USER')",
    // Declared here rather than inherited: this Patch lives on its own class, so the context on
    // BandSpaceMember's ApiResource does not reach it, and without it a null profile picture
    // vanishes from the body instead of being reported as absent.
    normalizationContext: ['skip_null_values' => false],
    // Nothing to read: the processor resolves the target membership itself so it can apply the
    // self-or-admin rule before anything else happens.
    read: false,
    output: BandSpaceMember::class,
    name: 'api_band_space_member_profile_patch',
    processor: BandSpaceMemberProfileUpdateProcessor::class,
)]
class BandSpaceMemberProfile
{
    /**
     * Capped at the column length. Blank is not "no name", it is the username fallback, which the
     * processor stores as null so there is one representation of "nothing chosen".
     */
    #[Assert\Length(max: 60, maxMessage: 'Le nom de scène ne peut pas dépasser {{ limit }} caractères')]
    public ?string $stageName = null;

    /**
     * Six is well past a real line-up entry and stops a rider line growing without limit. The ids
     * themselves are checked against the catalogue in the processor, which is the only place that
     * can tell an unknown id from a known one.
     *
     * @var list<mixed>
     */
    #[Assert\Count(max: 6, maxMessage: 'Un membre ne peut pas avoir plus de {{ limit }} instruments')]
    public array $instrumentIds = [];
}
