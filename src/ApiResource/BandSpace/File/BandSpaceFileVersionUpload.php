<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\State\Processor\BandSpace\File\BandSpaceFileVersionUploadProcessor;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    shortName: 'BandSpaceFileVersionUpload',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{fileId}/versions',
            inputFormats: ['multipart' => ['multipart/form-data']],
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            openapi: new Operation(
                tags: ['Band Space File Version'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileVersionResource::class,
            name: 'api_band_space_file_versions_upload',
            processor: BandSpaceFileVersionUploadProcessor::class,
        ),
    ],
)]
class BandSpaceFileVersionUpload
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    #[Vich\UploadableField(mapping: 'band_space_file', fileNameProperty: 'storagePath')]
    #[Assert\NotNull(message: 'Veuillez sélectionner un fichier')]
    #[Assert\File(maxSize: BandSpaceFileMimeAllowlist::MAX_UPLOAD_SIZE_BYTES)]
    public ?File $uploadedFile = null;

    public ?string $storagePath = null;
}
