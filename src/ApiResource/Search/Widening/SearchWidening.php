<?php

declare(strict_types=1);

namespace App\ApiResource\Search\Widening;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Attribute\Instrument as InstrumentEntity;
use App\Entity\Attribute\Style as StyleEntity;
use App\Entity\Musician\MusicianAnnounce;
use App\State\ParameterProvider\UuidLinkParameterProvider;
use App\State\Provider\Search\SearchWideningProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * For a musician search that found nothing (#1084): which wider search would find something. Yes or
 * no only, never how many, as the guided search shows no counts.
 */
#[Get(
    uriTemplate: '/musicians/search/widen',
    openapi: new Operation(tags: ['Musician announce']),
    name: 'api_musician_search_widen',
    provider: SearchWideningProvider::class,
    // The musician search's own criteria, repeated: an attribute cannot share them through a call.
    parameters: [
        'type' => new QueryParameter(
            key: 'type',
            schema: ['enum' => [MusicianAnnounce::TYPE_MUSICIAN_STR, MusicianAnnounce::TYPE_BAND_STR]],
            required: false,
        ),
        'instrument' => new QueryParameter(
            key: 'instrument',
            provider: UuidLinkParameterProvider::class,
            required: false,
            extraProperties: [
                'resource_class' => InstrumentEntity::class,
                'invalid_message' => "L'instrument n'est pas valide",
                'not_found_message' => 'Instrument introuvable',
            ],
        ),
        'styles' => new QueryParameter(
            key: 'styles',
            provider: UuidLinkParameterProvider::class,
            extraProperties: [
                'resource_class' => StyleEntity::class,
                'list' => true,
                'invalid_message' => "Le style n'est pas valide",
                'not_found_message' => 'Style introuvable',
            ],
        ),
        'latitude' => new QueryParameter(key: 'latitude', schema: ['type' => 'number', 'format' => 'float']),
        'longitude' => new QueryParameter(key: 'longitude', schema: ['type' => 'number', 'format' => 'float']),
        'radius' => new QueryParameter(
            key: 'radius',
            schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: '/^\d+\z/', message: 'La distance doit être un nombre entier de kilomètres'),
                new Assert\Range(min: 1, max: 500, notInRangeMessage: 'La distance doit être comprise entre {{ min }} et {{ max }} km'),
            ])],
        ),
    ],
)]
class SearchWidening
{
    /** The distance a wider search goes to. */
    public const int WIDER_RADIUS = 100;

    /** Searching up to WIDER_RADIUS km would find something. */
    public bool $widerRadius = false;
    public int $widerRadiusKm = self::WIDER_RADIUS;

    /** Searching every style would find something. */
    public bool $allStyles = false;
}
