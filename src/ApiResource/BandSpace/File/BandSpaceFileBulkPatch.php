<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileBulkPatchProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/files/bulk_patch',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: BandSpaceFileResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space File']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_files_bulk_patch',
    processor: BandSpaceFileBulkPatchProcessor::class,
)]
class BandSpaceFileBulkPatch
{
    /** @var string[] */
    #[Assert\Count(
        min: 1,
        max: 2000,
        minMessage: 'Au moins un fichier doit être sélectionné',
        maxMessage: 'Au maximum {{ limit }} fichiers peuvent être modifiés en une fois',
    )]
    #[Assert\All([new Assert\NotBlank()])]
    public array $fileIds = [];

    /**
     * Declared for the OpenAPI schema only. The processor re-reads the raw request, because a move to
     * the root sends null and presence has to be told apart from absence.
     */
    public ?string $folderId = null;
}
