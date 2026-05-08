<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFolderCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceFolderCreate',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/folders',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Folder']),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFolderResource::class,
            name: 'api_band_space_folders_post',
            processor: BandSpaceFolderCreateProcessor::class,
        ),
    ],
)]
class BandSpaceFolderCreate
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un nom de dossier')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public string $name;

    public ?string $parentId = null;
}
