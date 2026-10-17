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
                new Assert\Type(type: 'string', message: 'geocoding.query.not_text'),
                new Assert\NotBlank(message: 'geocoding.query.required', normalizer: 'trim'),
                // No control character (a null byte included): nothing a city name holds, and Photon refuses them.
                new Assert\Regex(pattern: '/^[^\x00-\x1f\x7f]+\z/u', message: 'geocoding.query.invalid_characters'),
                new Assert\Length(
                    min: 2,
                    max: 100,
                    minMessage: 'geocoding.query.too_short',
                    maxMessage: 'geocoding.query.too_long',
                ),
            ])],
        ),
        'limit' => new QueryParameter(
            key: 'limit',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: '/^\d+\z/', message: 'geocoding.limit.not_integer'),
                new Assert\Range(min: 1, max: 10, notInRangeMessage: 'geocoding.limit.out_of_range'),
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
                new Assert\NotBlank(message: 'geocoding.latitude.required'),
                new Assert\Regex(pattern: self::COORDINATE_PATTERN, message: 'geocoding.latitude.invalid'),
                new Assert\Range(min: -90, max: 90, notInRangeMessage: 'geocoding.latitude.out_of_range'),
            ])],
        ),
        'longitude' => new QueryParameter(
            key: 'longitude',
            required: true,
            constraints: [new Assert\Sequentially([
                new Assert\NotBlank(message: 'geocoding.longitude.required'),
                new Assert\Regex(pattern: self::COORDINATE_PATTERN, message: 'geocoding.longitude.invalid'),
                new Assert\Range(min: -180, max: 180, notInRangeMessage: 'geocoding.longitude.out_of_range'),
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
