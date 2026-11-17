<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\View\ServiceAudioView;
use App\Serializer\Encoder\MultipartDecoder;
use App\State\Processor\Service\MyServiceAudioDeleteProcessor;
use App\State\Processor\Service\MyServiceAudioUploadProcessor;
use Symfony\Component\HttpFoundation\Response;

/** The owner's « Avant, après » (#1410): a new upload replaces the side's sound. */
#[ApiResource(
    shortName: 'MyServiceAudio',
    operations: [
        new Post(
            inputFormats: ['multipart' => ['multipart/form-data']],
            status: Response::HTTP_OK,
            denormalizationContext: [MultipartDecoder::RAW_FIELDS => ['title', 'credit']],
            read: false,
            input: MyServiceAudioInput::class,
            name: 'api_user_services_audio_upload',
            processor: MyServiceAudioUploadProcessor::class,
        ),
        // Not served, only the address an upload answers with.
        new NotExposed(),
        new Delete(
            read: false,
            name: 'api_user_services_audio_delete',
            processor: MyServiceAudioDeleteProcessor::class,
        ),
    ],
    uriTemplate: '/user/services/{id}/before-after/{side}',
    uriVariables: [
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
        'side' => new Link(fromClass: self::class, identifiers: ['side']),
    ],
    requirements: ['id' => '[0-9a-fA-F\-]{36}', 'side' => 'before|after'],
    openapi: new Operation(tags: ['Service']),
    security: 'is_granted("ROLE_USER")',
)]
class MyServiceAudio extends ServiceAudioView
{
    #[ApiProperty(identifier: true)]
    public string $id;

    /** A BeforeAfterSide value. */
    #[ApiProperty(identifier: true)]
    public string $side;
}
