<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceNoteCreateProcessor;
use App\Validator\BandSpace\NoteMaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/notes',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: BandSpaceNote::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Note']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: BandSpaceNote::class,
    name: 'api_band_space_notes_post',
    processor: BandSpaceNoteCreateProcessor::class,
)]
#[NoteMaxDepth]
class BandSpaceNoteCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(
        min: 1,
        max: 255,
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $title;

    #[Assert\Uuid(message: 'Identifiant de note parent invalide')]
    public ?string $parentId = null;
}
