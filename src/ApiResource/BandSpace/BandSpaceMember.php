<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceMemberDeleteProcessor;
use App\State\Processor\BandSpace\BandSpaceMemberUpdateRoleProcessor;
use App\State\Provider\BandSpace\BandSpaceMemberCollectionProvider;
use App\State\Provider\BandSpace\BandSpaceMemberItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/members',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Member']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_members_get_collection',
            provider: BandSpaceMemberCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/members/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Member']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_members_get_item',
            provider: BandSpaceMemberItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/members/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Member']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_members_patch',
            provider: BandSpaceMemberItemProvider::class,
            processor: BandSpaceMemberUpdateRoleProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/members/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Member']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_members_delete',
            provider: BandSpaceMemberItemProvider::class,
            processor: BandSpaceMemberDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceMember
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $userId;
    public string $username;

    #[Assert\Choice(choices: ['admin', 'user'], message: 'Le rôle doit être "admin" ou "user"')]
    public string $role;

    public string $creationDatetime;
}
