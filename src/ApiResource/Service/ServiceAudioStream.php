<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Service\ServiceAudioStreamProvider;

/** One sound of a service's « Avant, après » (#1410), streamed to whoever may read its page. */
#[Get(
    uriTemplate: '/services/{id}/before-after/{side}',
    uriVariables: [
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
        'side' => new Link(fromClass: self::class, identifiers: ['side']),
    ],
    requirements: ['id' => '[0-9a-fA-F\-]{36}', 'side' => 'before|after'],
    openapi: new Operation(tags: ['Service']),
    output: false,
    name: 'api_service_audio_get',
    provider: ServiceAudioStreamProvider::class,
)]
class ServiceAudioStream
{
    #[ApiProperty(identifier: true)]
    public string $id;

    /** A BeforeAfterSide value. */
    #[ApiProperty(identifier: true)]
    public string $side;
}
