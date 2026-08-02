<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderItemCreateProcessor;
use App\Enum\BandSpace\TechRiderItemType;
use App\Validator\BandSpace\TechRider\TechRiderItemContent;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['bandSpaceId']),
        'riderId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['riderId']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => [TechRiderItemResource::READ], 'skip_null_values' => false],
    output: TechRiderItemResource::class,
    name: 'api_band_space_tech_rider_items_post',
    processor: TechRiderItemCreateProcessor::class,
)]
class TechRiderItemCreate
{
    /** Defaults to a text item, which is the only type with a renderer today. */
    #[Assert\Choice(callback: [TechRiderItemType::class, 'values'], message: 'Type d\'élément inconnu')]
    public string $type = 'text';

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    /** @var array<string, mixed>|null */
    #[TechRiderItemContent]
    public ?array $content = null;
}
