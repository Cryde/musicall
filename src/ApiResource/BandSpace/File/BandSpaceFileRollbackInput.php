<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileRollbackProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceFileRollback',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{fileId}/rollback',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            openapi: new Operation(tags: ['Band Space File Version']),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_file_versions_rollback',
            processor: BandSpaceFileRollbackProcessor::class,
        ),
    ],
)]
class BandSpaceFileRollbackInput
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    #[Assert\NotNull(message: 'Le numéro de version est requis')]
    #[Assert\Positive(message: 'Le numéro de version doit être un entier positif')]
    public ?int $versionNumber = null;
}
