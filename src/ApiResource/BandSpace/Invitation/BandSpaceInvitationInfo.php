<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Invitation;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\BandSpaceInvitationInfoProvider;

#[ApiResource(
    shortName: 'BandSpaceInvitationInfo',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/invitations/{token}/info',
            uriVariables: [
                'token' => new Link(fromClass: self::class, identifiers: ['token']),
            ],
            openapi: new Operation(tags: ['Band Space Invitation']),
            name: 'api_band_space_invitations_info',
            provider: BandSpaceInvitationInfoProvider::class,
        ),
    ],
)]
class BandSpaceInvitationInfo
{
    #[ApiProperty(identifier: true)]
    public string $token;

    public string $email;
    public string $bandSpaceName;
}
