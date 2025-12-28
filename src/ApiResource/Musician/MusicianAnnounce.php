<?php declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Musician\Announce\Author;
use App\ApiResource\Musician\Announce\Instrument;
use App\ApiResource\Musician\Announce\Style;
use App\State\Processor\Musician\AnnounceDeleteProcessor;
use App\State\Provider\Musician\AnnounceDeleteProvider;
use App\State\Provider\Musician\MusicianAnnounceItemProvider;
use App\State\Provider\Musician\MusicianAnnounceLastProvider;
use App\State\Provider\Musician\MusicianAnnounceSelfProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/musician_announces/last',
            openapi: new Operation(tags: ['Musician announce']),
            paginationEnabled: false,
            name: 'api_musician_announces_get_last_collection',
            provider: MusicianAnnounceLastProvider::class
        ),
        new GetCollection(
            uriTemplate: '/musician_announces/self',
            openapi: new Operation(tags: ['Musician announce']),
            paginationEnabled: false,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_musician_announces_get_self_collection',
            provider: MusicianAnnounceSelfProvider::class
        ),
        new Get(
            uriTemplate: '/musician_announces/{id}',
            openapi: new Operation(tags: ['Musician announce']),
            name: 'api_musician_announces_get_item',
            provider: MusicianAnnounceItemProvider::class
        ),
        new Delete(
            uriTemplate: '/user/musician/announces/{id}',
            openapi: new Operation(tags: ['Musician announce']),
            name: 'api_musician_announces_delete',
            provider: AnnounceDeleteProvider::class,
            processor: AnnounceDeleteProcessor::class,
        ),
    ]
)]
class MusicianAnnounce
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public ?\DateTimeInterface $creationDatetime = null;

    public int $type;

    #[ApiProperty(genId: false)]
    public Instrument $instrument;

    /** @var Style[] */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    public string $locationName;

    public ?string $note = null;

    #[ApiProperty(genId: false)]
    public ?Author $author = null;
}
