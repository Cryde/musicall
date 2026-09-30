<?php

declare(strict_types=1);

namespace App\ApiResource\Geocoding;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Service\Geocoding\City;
use App\State\Provider\Geocoding\CityReverseProvider;
use App\State\Provider\Geocoding\CitySearchProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The city pickers' suggestions, proxied to Photon so a visitor's typing and position stay between
 * them and us. One object holding a list, rather than a collection, because a suggestion has no
 * identity of its own to hang an IRI on.
 */
#[Get(
    uriTemplate: '/geocoding/cities',
    openapi: false,
    name: 'api_geocoding_cities',
    provider: CitySearchProvider::class,
    parameters: [
        'q' => new QueryParameter(
            key: 'q',
            required: true,
            constraints: [new Assert\Sequentially([
                new Assert\Type(type: 'string', message: 'La recherche doit être un texte'),
                new Assert\NotBlank(message: 'Saisissez une ville', normalizer: 'trim'),
                // No control character (a null byte included): nothing a city name holds, and Photon refuses them.
                new Assert\Regex(pattern: '/^[^\x00-\x1f\x7f]+\z/u', message: 'La recherche contient des caractères invalides'),
                new Assert\Length(
                    min: 2,
                    max: 100,
                    minMessage: 'Saisissez au moins {{ limit }} caractères',
                    maxMessage: 'La recherche ne peut pas dépasser {{ limit }} caractères',
                ),
            ])],
        ),
        'limit' => new QueryParameter(
            key: 'limit',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: '/^\d+\z/', message: 'Le nombre de suggestions doit être un entier'),
                new Assert\Range(min: 1, max: 10, notInRangeMessage: 'Le nombre de suggestions doit être compris entre {{ min }} et {{ max }}'),
            ])],
        ),
    ],
)]
#[Get(
    uriTemplate: '/geocoding/reverse',
    openapi: false,
    name: 'api_geocoding_reverse',
    provider: CityReverseProvider::class,
    parameters: [
        'latitude' => new QueryParameter(
            key: 'latitude',
            required: true,
            constraints: [new Assert\Sequentially([
                new Assert\NotBlank(message: 'Précisez une latitude'),
                new Assert\Regex(pattern: self::COORDINATE_PATTERN, message: 'La latitude est invalide'),
                new Assert\Range(min: -90, max: 90, notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }}'),
            ])],
        ),
        'longitude' => new QueryParameter(
            key: 'longitude',
            required: true,
            constraints: [new Assert\Sequentially([
                new Assert\NotBlank(message: 'Précisez une longitude'),
                new Assert\Regex(pattern: self::COORDINATE_PATTERN, message: 'La longitude est invalide'),
                new Assert\Range(min: -180, max: 180, notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }}'),
            ])],
        ),
    ],
)]
class CityLookup
{
    public const string COORDINATE_PATTERN = '/^-?\d{1,3}(\.\d+)?\z/';

    /** @var list<City> */
    #[ApiProperty(genId: false)]
    public array $cities = [];

    /** @param list<City> $cities */
    public static function of(array $cities): self
    {
        $lookup = new self();
        $lookup->cities = $cities;

        return $lookup;
    }
}
