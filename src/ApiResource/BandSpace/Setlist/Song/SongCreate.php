<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Song\SongCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/songs',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SongResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: SongResource::class,
    name: 'api_band_space_songs_post',
    processor: SongCreateProcessor::class,
)]
class SongCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    #[Assert\Range(min: 1, max: 400, notInRangeMessage: 'Le tempo doit être entre {{ min }} et {{ max }} BPM')]
    public ?int $tempo = null;

    #[Assert\Length(max: 16, maxMessage: 'La tonalité ne peut pas dépasser {{ limit }} caractères')]
    public ?string $tonality = null;

    #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} secondes')]
    public ?int $referenceDuration = null;

    public ?string $notes = null;
}
