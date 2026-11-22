<?php

declare(strict_types=1);

namespace App\ApiResource\Service\SoundEngineering;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\Edit\MyServiceForm;
use App\ApiResource\Service\View\ServiceAudioView;
use App\Entity\Service\SoundEngineeringDetails;
use App\Enum\Service\DeliveryFormat;
use App\Enum\Service\ServiceKind;
use App\State\Processor\Service\MySoundEngineeringProcessor;
use App\State\Provider\Service\MySoundEngineeringProvider;
use Symfony\Component\Validator\Constraints as Assert;

/** A member's own sound engineering service, as its form edits it (#1410): what every kind's form edits, and its own. */
#[ApiResource(
    shortName: 'MySoundEngineering',
    operations: [
        new Get(name: 'api_user_services_sound_engineering_get'),
        new Patch(name: 'api_user_services_sound_engineering_patch', processor: MySoundEngineeringProcessor::class),
    ],
    uriTemplate: '/user/services/{id}/sound-engineering',
    requirements: ['id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    security: 'is_granted("ROLE_USER")',
    provider: MySoundEngineeringProvider::class,
)]
class MySoundEngineering extends MyServiceForm
{
    private const int MAX_GEAR_LENGTH = 40;

    #[Assert\Range(notInRangeMessage: 'service.turnaround_invalid', min: 1, max: 365)]
    public ?int $turnaroundDays = null;

    #[Assert\Range(notInRangeMessage: 'service.revisions_invalid', min: 0, max: 20)]
    public ?int $revisionsIncluded = null;

    /** @var array<string> DeliveryFormat values */
    #[Assert\Unique]
    #[Assert\All([new Assert\Choice(callback: [DeliveryFormat::class, 'values'], message: 'service.format_invalid')])]
    public array $formats = [];

    /** @var array<string> */
    #[Assert\Count(max: SoundEngineeringDetails::MAX_GEAR, maxMessage: 'service.gear_too_many')]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank(), new Assert\Length(max: self::MAX_GEAR_LENGTH)])]
    public array $gear = [];

    /** @var ServiceAudioView[] */
    #[ApiProperty(writable: false, genId: false)]
    public array $beforeAfter = [];

    public static function kind(): ServiceKind
    {
        return ServiceKind::SoundEngineering;
    }
}
