<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderSectionCreateProcessor;
use App\Validator\BandSpace\TechRider\TechRiderSectionContent;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/sections',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderSectionResource::class, identifiers: ['bandSpaceId']),
        'riderId' => new Link(fromClass: TechRiderSectionResource::class, identifiers: ['riderId']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => [TechRiderSectionResource::READ], 'skip_null_values' => false],
    output: TechRiderSectionResource::class,
    name: 'api_band_space_tech_rider_sections_post',
    processor: TechRiderSectionCreateProcessor::class,
)]
class TechRiderSectionCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    /** @var array<string, mixed>|null */
    #[TechRiderSectionContent]
    public ?array $content = null;
}
