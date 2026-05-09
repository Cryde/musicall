<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileDeleteProcessor;
use App\State\Processor\BandSpace\File\BandSpaceFileUpdateProcessor;
use App\State\Processor\BandSpace\File\BandSpaceTaskFileDetachProcessor;
use App\State\Provider\BandSpace\File\BandSpaceFileCollectionProvider;
use App\State\Provider\BandSpace\File\BandSpaceFileItemProvider;
use App\State\Provider\BandSpace\File\BandSpaceTaskFileCollectionProvider;

#[ApiResource(
    shortName: 'BandSpaceFile',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/files',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 200,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_files_get_collection',
            provider: BandSpaceFileCollectionProvider::class,
            parameters: [
                'folder_id' => new QueryParameter(key: 'folder_id'),
                'tag_id' => new QueryParameter(key: 'tag_id'),
                'source' => new QueryParameter(key: 'source'),
                'query' => new QueryParameter(key: 'query'),
                'mime' => new QueryParameter(key: 'mime'),
                'uploader_id' => new QueryParameter(key: 'uploader_id'),
                'sort' => new QueryParameter(key: 'sort'),
                'order' => new QueryParameter(key: 'order'),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_files_get_item',
            provider: BandSpaceFileItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_files_patch',
            provider: BandSpaceFileItemProvider::class,
            processor: BandSpaceFileUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_files_delete',
            provider: BandSpaceFileItemProvider::class,
            processor: BandSpaceFileDeleteProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{taskId}/files',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'taskId' => new Link(fromClass: self::class, identifiers: ['taskId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 200,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_task_files_get_collection',
            provider: BandSpaceTaskFileCollectionProvider::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{taskId}/files/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'taskId' => new Link(fromClass: self::class, identifiers: ['taskId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_task_files_detach',
            provider: BandSpaceFileItemProvider::class,
            processor: BandSpaceTaskFileDetachProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFileResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $originalName;
    public ?int $size = null;
    public ?string $mimeType = null;
    public ?string $folderId = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $folderPath = [];

    /** @var array<int, array{id: string, name: string, color_hex: string|null}> */
    public array $tags = [];

    public ?string $attachedSourceType = null;
    public ?string $attachedSourceId = null;
    public ?string $currentVersionId = null;
    public int $versionCount = 0;

    /** @var array{id: string, username: string, profile_picture_url: string|null}|null */
    public ?array $createdBy = null;

    public ?string $downloadUrl = null;

    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
