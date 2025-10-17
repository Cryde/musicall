<?php declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Musician\AnnounceDeleteProcessor;
use App\State\Provider\Musician\AnnounceDeleteProvider;

#[Delete(
    uriTemplate: '/user/musician/announces/{id}',
    openapi: new Operation(tags: ['Musician announce']),
    name: 'api_musician_announces_delete',
    provider: AnnounceDeleteProvider::class,
    processor: AnnounceDeleteProcessor::class,
)]
class Announce
{
}
