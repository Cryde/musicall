<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Invitation;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceInvitationAcceptProcessor;

#[ApiResource(
    shortName: 'BandSpaceInvitationAccept',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/invitations/{token}/accept',
            uriVariables: [
                'token' => new Link(fromClass: self::class, identifiers: ['token']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            security: "is_granted('ROLE_USER')",
            input: false,
            name: 'api_band_space_invitations_accept',
            processor: BandSpaceInvitationAcceptProcessor::class,
        ),
    ],
)]
class BandSpaceInvitationAccept
{
    #[ApiProperty(identifier: true)]
    public string $token;

    public string $bandSpaceId;
    public string $bandSpaceName;
}
