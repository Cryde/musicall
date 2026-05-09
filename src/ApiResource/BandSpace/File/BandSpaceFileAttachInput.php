<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileAttachProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceFileAttach',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{fileId}/attach',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_files_attach_existing',
            processor: BandSpaceFileAttachProcessor::class,
        ),
    ],
)]
class BandSpaceFileAttachInput
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    #[Assert\NotBlank(message: 'Le type de source est requis')]
    #[Assert\Choice(choices: ['task', 'finance'], message: 'Type de source invalide')]
    public ?string $sourceType = null;

    #[Assert\NotBlank(message: "L'identifiant de la source est requis")]
    #[Assert\Uuid(message: 'Identifiant de source invalide')]
    public ?string $sourceId = null;
}
