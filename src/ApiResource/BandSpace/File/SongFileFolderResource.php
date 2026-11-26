<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\SongFileFolderDeleteProcessor;
use App\State\Processor\BandSpace\File\SongFileFolderRenameProcessor;
use App\State\Provider\BandSpace\File\SongFileFolderCollectionProvider;
use App\State\Provider\BandSpace\File\SongFileFolderItemProvider;

/**
 * A folder inside a song's files (#1451). Which folder a file sits in is told by the file itself, as
 * `song_folder_id` on its song attachment.
 */
#[ApiResource(
    shortName: 'SongFileFolder',
    operations: [
        // Every song's folders at once: the Files tree, and the names already used in the space.
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/song_file_folders',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_song_file_folders_get_collection',
            provider: SongFileFolderCollectionProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/file_folders',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'songId' => new Link(fromClass: self::class, identifiers: ['songId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_song_file_folders_get_song_collection',
            provider: SongFileFolderCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/file_folders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'songId' => new Link(fromClass: self::class, identifiers: ['songId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_song_file_folders_get_item',
            provider: SongFileFolderItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/file_folders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'songId' => new Link(fromClass: self::class, identifiers: ['songId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            input: SongFileFolderRename::class,
            read: false,
            name: 'api_band_space_song_file_folders_patch',
            processor: SongFileFolderRenameProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/file_folders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'songId' => new Link(fromClass: self::class, identifiers: ['songId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_band_space_song_file_folders_delete',
            processor: SongFileFolderDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class SongFileFolderResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $songId;

    public ?string $parentId = null;

    public string $name;

    public \DateTimeInterface $creationDatetime;
}
