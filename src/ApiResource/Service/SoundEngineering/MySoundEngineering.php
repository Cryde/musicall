<?php

declare(strict_types=1);

namespace App\ApiResource\Service\SoundEngineering;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\Edit\MyServiceOffer;
use App\ApiResource\Service\Edit\MyServicePlace;
use App\ApiResource\Service\View\ServiceAudioView;
use App\ApiResource\Service\View\ServiceWorkView;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Entity\Service\SoundEngineeringDetails;
use App\Enum\Service\DeliveryFormat;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\SoundEngineeringSpeciality;
use App\State\Processor\Service\MySoundEngineeringProcessor;
use App\State\Provider\Service\MySoundEngineeringProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A member's own sound engineering service, as its form edits it (#1410). The PATCH reads it first and merges the
 * body in (#1327), so a field left out keeps what is stored, and one sent as null clears it.
 */
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
class MySoundEngineering
{
    private const int MAX_PLACES = 3;
    private const int MAX_OFFERS = 12;
    private const int MAX_STYLES = 10;
    private const int MAX_GEAR_LENGTH = 40;
    public const string OFFER_UNIT_INVALID = 'music_all_eb832ebf-bf8b-4899-be88-eb5b80608707';

    #[ApiProperty(identifier: true, writable: false)]
    public string $id;

    /** A ServiceStatus value: publishing goes through « Mes services ». */
    #[ApiProperty(writable: false)]
    public string $status;

    #[Assert\Length(max: 150)]
    public ?string $headline = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\Range(notInRangeMessage: 'service.years_of_experience_invalid', min: 0, max: 70)]
    public ?int $yearsOfExperience = null;

    /** @var array<string> SoundEngineeringSpeciality values */
    #[Assert\Unique]
    #[Assert\All([new Assert\Choice(callback: [SoundEngineeringSpeciality::class, 'values'], message: 'service.speciality_invalid')])]
    public array $specialities = [];

    /** @var array<string> */
    #[Assert\Count(max: self::MAX_STYLES)]
    public array $styleIds = [];

    /** @var TeacherProfileStyle[] */
    #[ApiProperty(writable: false, genId: false)]
    public array $styles = [];

    /** @var MyServicePlace[] */
    #[ApiProperty(genId: false)]
    #[Assert\Count(max: self::MAX_PLACES)]
    #[Assert\Valid]
    public array $places = [];

    /** @var MyServiceOffer[] */
    #[ApiProperty(genId: false)]
    #[Assert\Count(max: self::MAX_OFFERS)]
    #[Assert\Valid]
    public array $offers = [];

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

    /** @var TeacherProfileMedia[] */
    #[ApiProperty(writable: false, genId: false)]
    public array $media = [];

    /** @var ServiceAudioView[] */
    #[ApiProperty(writable: false, genId: false)]
    public array $beforeAfter = [];

    /** @var ServiceWorkView[] « Réalisations » (#1407), each written through its own endpoint */
    #[ApiProperty(writable: false, genId: false)]
    public array $works = [];

    #[Assert\Callback]
    public function validateOfferUnits(ExecutionContextInterface $context): void
    {
        $units = array_map(static fn (OfferUnit $unit): string => $unit->value, ServiceKind::SoundEngineering->offerUnits());
        foreach ($this->offers as $index => $offer) {
            if ($offer->unit !== null && $offer->unit !== '' && !in_array($offer->unit, $units, true)) {
                $context->buildViolation('service.offer.unit_invalid')
                    ->atPath(sprintf('offers[%d].unit', $index))
                    ->setCode(self::OFFER_UNIT_INVALID)
                    ->addViolation();
            }
        }
    }
}
