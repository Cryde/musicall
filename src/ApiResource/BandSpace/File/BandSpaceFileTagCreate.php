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

    #[Assert\NotBlank(message: 'Veuillez spécifier un nom de tag')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public string $name;

    #[Assert\Regex(
        pattern: '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/',
        message: 'La couleur doit être au format hexadécimal (#RGB ou #RRGGBB)',
    )]
    public ?string $colorHex = null;
}
