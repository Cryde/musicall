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
    #[Assert\Count(min: 1, max: 10)]
    public array $styles = [];

    #[Assert\NotBlank]
    public string $locationName;

    #[Assert\NotBlank]
    public string $longitude;

    #[Assert\NotBlank]
    public string $latitude;

    public ?string $note = null;

    /**
     * Optional, so a client that does not ask (the mobile app) still gets the author's locale.
     *
     * @var list<string>|null
     */
    #[Assert\Sequentially([
        // The serializer reads a JSON object (`{"a": "fr"}`) into a keyed array, which JSON_CONTAINS never matches.
        new Assert\Type('list', message: 'announce.languages.invalid'),
        new Assert\Count(min: 1, minMessage: 'announce.languages.required'),
        new Assert\All([
            new Assert\NotNull(message: 'announce.languages.invalid'),
            new Assert\Choice(choices: MusicianAnnounceEntity::LANGUAGES, message: 'announce.languages.invalid'),
        ]),
        new Assert\Unique(message: 'announce.languages.duplicate'),
    ])]
    public ?array $languages = null;
}
