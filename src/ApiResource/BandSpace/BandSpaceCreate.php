<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces',
    openapi: new Operation(tags: ['Band Space']),
    security: 'is_granted("ROLE_USER")',
    output: BandSpace::class,
    // Same context as the BandSpace resource itself: the front-end merges this response into the list
    // fed by GET /band_spaces, so both have to serialise the DTO identically, nulls included.
    normalizationContext: ['skip_null_values' => false],
    name: 'api_band_spaces_post_collection',
    processor: BandSpaceCreateProcessor::class,
)]
class BandSpaceCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $name;
}
