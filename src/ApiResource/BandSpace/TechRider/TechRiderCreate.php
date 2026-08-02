<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: [
        'groups' => [TechRiderResource::ITEM, TechRiderSectionResource::READ],
        'skip_null_values' => false,
    ],
    output: TechRiderResource::class,
    name: 'api_band_space_tech_riders_post',
    processor: TechRiderCreateProcessor::class,
)]
class TechRiderCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public string $name;
}
