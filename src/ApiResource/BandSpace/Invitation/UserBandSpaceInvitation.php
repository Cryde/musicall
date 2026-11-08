<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Invitation;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\UserBandSpaceInvitationCollectionProvider;

/**
 * The pending invitations addressed to the caller (#999), so someone with no band yet can answer one
 * without the email. Under /user/ because /band_spaces/invitations is caught by GET /band_spaces/{id}.
 */
#[ApiResource(
    shortName: 'UserBandSpaceInvitation',
    operations: [
        new GetCollection(
            uriTemplate: '/user/band-space-invitations',
            openapi: new Operation(tags: ['Band Space Invitation']),
            paginationEnabled: false,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_band_space_invitations_get_collection',
            provider: UserBandSpaceInvitationCollectionProvider::class,
        ),
        // Not served, only the @id of an invitation.
        new NotExposed(uriTemplate: '/user/band-space-invitations/{id}'),
    ],
)]
class UserBandSpaceInvitation
{
    #[ApiProperty(identifier: true)]
    public string $id;

    /** What the accept, decline and info routes take. */
    public string $token;

    public string $bandSpaceId;
    public string $bandSpaceName;
    public string $invitedByUsername;
    public \DateTimeInterface $creationDatetime;
}
