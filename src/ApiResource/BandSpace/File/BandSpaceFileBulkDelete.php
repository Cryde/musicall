<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileBulkDeleteProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/files/bulk_delete',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: BandSpaceFileResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space File']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_files_bulk_delete',
    processor: BandSpaceFileBulkDeleteProcessor::class,
)]
class BandSpaceFileBulkDelete
{
    /**
     * The maximum matches BandSpaceFolderDeleteProcessor::MAX_CASCADE_FILES, which #823 measured
     * rather than guessed, so the two paths that trash many files at once refuse at the same size.
     *
     * @var string[]
     */
    #[Assert\Count(
        min: 1,
        max: 2000,
        minMessage: 'Au moins un fichier doit être sélectionné',
        maxMessage: 'Au maximum {{ limit }} fichiers peuvent être supprimés en une fois',
    )]
    #[Assert\All([new Assert\NotBlank()])]
    public array $fileIds = [];
}
