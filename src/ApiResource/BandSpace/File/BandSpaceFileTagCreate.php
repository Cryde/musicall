<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileTagCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceFileTagCreate',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/tags',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space File Tag']),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileTagResource::class,
            name: 'api_band_space_file_tags_post',
            processor: BandSpaceFileTagCreateProcessor::class,
        ),
    ],
)]
class BandSpaceFileTagCreate
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'band_space.tag.name_required')]
    #[Assert\Length(max: 255, maxMessage: 'common.name.too_long')]
    public string $name;

    #[Assert\Regex(
        pattern: '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/',
        message: 'band_space.tag.color_invalid',
    )]
    public ?string $colorHex = null;
}
