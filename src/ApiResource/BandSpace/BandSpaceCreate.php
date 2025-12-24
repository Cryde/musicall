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
    name: 'api_band_spaces_post_collection',
    processor: BandSpaceCreateProcessor::class,
)]
class BandSpaceCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Band space name must be at least {{ limit }} characters long',
        maxMessage: 'Band space name cannot be longer than {{ limit }} characters'
    )]
    public string $name;
}
