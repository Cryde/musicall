<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Song\SongsArchiveProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Several songs moved to the trash at once, from the repertoire's selection (#1063).
 */
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/songs/archive',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SongResource::class, identifiers: ['bandSpaceId']),
    ],
    status: 204,
    openapi: new Operation(tags: ['Band Space Setlist']),
    security: "is_granted('ROLE_USER')",
    output: false,
    read: false,
    name: 'api_band_space_songs_archive_bulk',
    processor: SongsArchiveProcessor::class,
)]
class SongsArchive
{
    public const int MAX_SONGS = 200;

    /** @var list<string> */
    #[Assert\Count(min: 1, max: self::MAX_SONGS, minMessage: 'Choisissez au moins un titre', maxMessage: 'Pas plus de {{ limit }} titres à la fois')]
    #[Assert\All([new Assert\Uuid(message: 'Identifiant de titre invalide')])]
    public array $songIds = [];
}
