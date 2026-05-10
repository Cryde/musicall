<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Invitation;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceInvitationDeclineProcessor;

#[ApiResource(
    shortName: 'BandSpaceInvitationDecline',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/invitations/{token}/decline',
            uriVariables: [
                'token' => new Link(fromClass: self::class, identifiers: ['token']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: false,
            name: 'api_band_space_invitations_decline',
            processor: BandSpaceInvitationDeclineProcessor::class,
        ),
    ],
)]
class BandSpaceInvitationDecline
{
    #[ApiProperty(identifier: true)]
    public string $token;
}
