<?php

declare(strict_types=1);

namespace App\ApiResource\Service\SoundEngineering;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\ServicePage;
use App\ApiResource\Service\View\ServiceAudioView;
use App\State\Provider\Service\SoundEngineerPageProvider;

/** A sound engineer's page (#1410), `/u/{username}/sound-engineer`. */
#[Get(
    uriTemplate: '/user/profile/{username}/sound-engineer',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_sound_engineer_page_get',
    provider: SoundEngineerPageProvider::class,
)]
class SoundEngineerPage extends ServicePage
{
    public ?int $turnaroundDays = null;

    public ?int $revisionsIncluded = null;

    /** @var list<string> DeliveryFormat values */
    public array $formats = [];

    /** @var list<string> */
    public array $gear = [];

    /**
     * « Avant, après », before first.
     *
     * @var ServiceAudioView[]
     */
    #[ApiProperty(genId: false)]
    public array $beforeAfter = [];
}
