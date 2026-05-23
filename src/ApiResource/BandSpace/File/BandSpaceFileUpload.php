<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\State\Processor\BandSpace\File\BandSpaceFileUploadProcessor;
use App\State\Processor\BandSpace\File\BandSpaceFinanceEntryFileAttachProcessor;
use App\State\Processor\BandSpace\File\BandSpaceNoteFileAttachProcessor;
use App\State\Processor\BandSpace\File\BandSpaceTaskFileAttachProcessor;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    shortName: 'BandSpaceFileUpload',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Band Space File'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                    'folderId' => ['type' => 'string', 'nullable' => true],
                                    'attachedSourceType' => ['type' => 'string', 'nullable' => true],
                                    'attachedSourceId' => ['type' => 'string', 'nullable' => true],
                                    'tagIds' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_files_upload',
            processor: BandSpaceFileUploadProcessor::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{taskId}/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Band Space File'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                    'folderId' => ['type' => 'string', 'nullable' => true],
                                    'tagIds' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_task_files_attach',
            processor: BandSpaceTaskFileAttachProcessor::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{entryId}/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Band Space File'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                    'folderId' => ['type' => 'string', 'nullable' => true],
                                    'tagIds' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_finance_entry_files_attach',
            processor: BandSpaceFinanceEntryFileAttachProcessor::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/notes/{noteId}/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Band Space File'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_note_files_attach',
            processor: BandSpaceNoteFileAttachProcessor::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{songId}/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Band Space File'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                    'folderId' => ['type' => 'string', 'nullable' => true],
                                    'tagIds' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_song_files_attach',
            processor: \App\State\Processor\BandSpace\File\BandSpaceSongFileAttachProcessor::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{setlistId}/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Band Space File'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uploadedFile' => ['type' => 'string', 'format' => 'binary'],
                                    'folderId' => ['type' => 'string', 'nullable' => true],
                                    'tagIds' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            output: BandSpaceFileResource::class,
            name: 'api_band_space_setlist_files_attach',
            processor: \App\State\Processor\BandSpace\File\BandSpaceSetlistFileAttachProcessor::class,
        ),
    ],
)]
class BandSpaceFileUpload
{
    #[Vich\UploadableField(mapping: 'band_space_file', fileNameProperty: 'storagePath')]
    #[Assert\NotNull(message: 'Veuillez sélectionner un fichier')]
    #[Assert\File(maxSize: BandSpaceFileMimeAllowlist::MAX_UPLOAD_SIZE_BYTES)]
    public ?File $uploadedFile = null;

    public ?string $storagePath = null;

    public ?string $folderId = null;

    public ?string $attachedSourceType = null;

    public ?string $attachedSourceId = null;

    /** @var string[] */
    public array $tagIds = [];
}
