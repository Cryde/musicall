<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\BandSpace\SongFileFolder;
use App\State\Processor\BandSpace\File\SongFileFolderCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'SongFileFolderCreate',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/file_folders',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'songId' => new Link(fromClass: self::class, identifiers: ['songId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            output: SongFileFolderResource::class,
            name: 'api_band_space_song_file_folders_post',
            processor: SongFileFolderCreateProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class SongFileFolderCreate
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $songId;

    #[Assert\NotBlank(message: 'band_space.folder.name_required', normalizer: 'trim')]
    #[Assert\Length(max: SongFileFolder::NAME_MAX_LENGTH, maxMessage: 'common.name.too_long')]
    public string $name;

    /** Null puts the folder at the song's root. */
    public ?string $parentId = null;
}
