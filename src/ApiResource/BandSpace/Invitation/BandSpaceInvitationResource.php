<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Invitation;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceInvitationDeleteProcessor;
use App\State\Provider\BandSpace\BandSpaceInvitationCollectionProvider;
use App\State\Provider\BandSpace\BandSpaceInvitationItemProvider;

#[ApiResource(
    shortName: 'BandSpaceInvitation',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/invitations',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_invitations_get_collection',
            provider: BandSpaceInvitationCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/invitations/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_invitations_get_item',
            provider: BandSpaceInvitationItemProvider::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/invitations/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_invitations_delete',
            provider: BandSpaceInvitationItemProvider::class,
            processor: BandSpaceInvitationDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceInvitationResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $email;
    public string $status;
    public string $creationDatetime;
    public string $expirationDatetime;
}
