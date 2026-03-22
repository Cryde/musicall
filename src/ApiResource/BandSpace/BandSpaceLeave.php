<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceLeaveProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/leave',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Member']),
            status: 204,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_leave',
            input: false,
            output: false,
            processor: BandSpaceLeaveProcessor::class,
        ),
    ],
)]
class BandSpaceLeave
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;
}
