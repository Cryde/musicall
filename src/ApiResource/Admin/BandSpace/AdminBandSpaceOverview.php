<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Admin\BandSpace\AdminBandSpaceOverviewProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * How Band Spaces are used, for the admin (#1275). Spaces pending deletion are left out of every figure
 * but their own count. Module and chat usage cover the period, the rest is as of now.
 */
#[Get(
    uriTemplate: '/admin/band-spaces/overview',
    openapi: new Operation(tags: ['Admin Band Space']),
    security: 'is_granted("ROLE_ADMIN")',
    name: 'api_admin_band_spaces_overview',
    provider: AdminBandSpaceOverviewProvider::class,
    parameters: [
        'from' => new QueryParameter(key: 'from', constraints: [new Assert\Sequentially([new Assert\NotBlank(), new Assert\Date()])]),
        'to' => new QueryParameter(key: 'to', constraints: [new Assert\Sequentially([new Assert\NotBlank(), new Assert\Date()])]),
    ],
)]
class AdminBandSpaceOverview
{
    public string $from;
    public string $to;
    public int $spaces = 0;
    public int $pendingDeletionSpaces = 0;
    public int $activeSpaces = 0;
    public int $dormantSpaces = 0;
    public int $activeWindowDays = 0;
    public float $averageMembers = 0;
    public float $medianMembers = 0;
    public int $soloSpaces = 0;
    public int $chatMessages = 0;
    public int $chatSpaces = 0;
    public int $storageBytes = 0;

    /** @var list<AdminBandSpaceModuleUsage> */
    #[ApiProperty(genId: false)]
    public array $modules = [];

    /** @var list<AdminBandSpaceStorage> */
    #[ApiProperty(genId: false)]
    public array $topStorage = [];
}
