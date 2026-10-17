<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Invitation;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceInvitationCreateProcessor;
use App\Validator\BandSpace\InvitationIdentifier;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceInvitationCreate',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/invitations',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_invitations_post',
            processor: BandSpaceInvitationCreateProcessor::class,
        ),
    ],
)]
class BandSpaceInvitationCreate
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'band_space.invitation.identifier_required')]
    #[Assert\Length(max: 255, maxMessage: 'band_space.invitation.identifier_too_long')]
    #[InvitationIdentifier]
    public string $identifier;
}
