<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderDuplicateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Duplicating a rider is how the next one gets made.
 *
 * Riders are per tour or per year: next year's is this year's with a new drummer and one changed
 * backline line. Without this, people either edit last year's in place and lose it, or retype a
 * 24 row patch list.
 *
 * Unlike SetlistDuplicate this takes an optional name, because a rider's name is a year or a tour
 * and "Tech rider 2026 (copie)" is never what the band wanted to call it.
 */
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}/duplicate',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: TechRiderResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => [TechRiderResource::ITEM, TechRiderItemResource::READ], 'skip_null_values' => false],
    output: TechRiderResource::class,
    name: 'api_band_space_tech_riders_duplicate',
    processor: TechRiderDuplicateProcessor::class,
)]
class TechRiderDuplicate
{
    /**
     * Absent falls back to the source name plus a suffix. Blank is refused rather than falling back,
     * because sending an empty name is a client asking for something impossible, not a client
     * declining to choose.
     *
     * Normalised with trim, because NotBlank does not consider a run of spaces blank on its own.
     */
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim', message: 'Veuillez spécifier un nom')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public ?string $name = null;
}
