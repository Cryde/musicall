<?php declare(strict_types=1);

namespace App\ApiResource\Search;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Search\AnnounceMusicianFilterProvider;
use Symfony\Component\Validator\Constraints\Length;

#[Get(
    uriTemplate: 'musicians/filters',
    openapi: new Operation(tags: ['Musician announce']),
    description: 'Generate musicians announce filters from a textual search ',
    name: 'api_musician_announces_filters',
    provider: AnnounceMusicianFilterProvider::class,
    parameters: [
        'search'       => new QueryParameter(
            key: 'search',
            description: 'The textual search that will be converted as filters',
            required: true,
            constraints: [
                new Length(
                    min: 10,
                    max: 200,
                    minMessage: 'Cette recherche est trop courte (min {{ limit }} caractères)',
                    maxMessage: 'Cette recherche est trop longue (max {{ limit }} caractères)'
                )
            ],
        ),
    ],
)]
class AnnounceMusicianFilter
{
    public int $type;
    public string $instrument;
    /** @var string[] */
    public array $styles = [];
    public ?float $latitude = null;
    public ?float $longitude = null;
}
