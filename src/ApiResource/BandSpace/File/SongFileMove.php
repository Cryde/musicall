<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\SongFileMoveProcessor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts a song's file in one of the song's folders (#1451). The file stays where it is in Files.
 */
#[ApiResource(
    shortName: 'SongFileMove',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/files/{fileId}/folder',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'songId' => new Link(fromClass: self::class, identifiers: ['songId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            status: Response::HTTP_OK,
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_song_files_move',
            processor: SongFileMoveProcessor::class,
        ),
    ],
)]
class SongFileMove
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $songId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    /** Null puts the file back at the song's root. */
    public ?string $folderId = null;
}
