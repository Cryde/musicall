<?php declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce as MusicianAnnounceEntity;
use App\State\Processor\Musician\MusicianAnnouncePostProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/musician_announces',
    openapi: new Operation(tags: ['Musician announce']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    output: MusicianAnnounce::class,
    name: 'api_musician_announces_post',
    processor: MusicianAnnouncePostProcessor::class
)]
class MusicianAnnounceCreate
{
    #[Assert\NotNull]
    #[Assert\Choice(choices: MusicianAnnounceEntity::TYPES)]
    public int $type;

    #[Assert\NotNull]
    public Instrument $instrument;

    /** @var Style[] */
    #[Assert\Count(min: 1)]
    public array $styles = [];

    #[Assert\NotBlank]
    public string $locationName;

    #[Assert\NotBlank]
    public string $longitude;

    #[Assert\NotBlank]
    public string $latitude;

    public ?string $note = null;
}
