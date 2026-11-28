<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Edit;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Service\View\ServiceWorkView;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\ServiceSpeciality;
use App\Validator\Service\ExperienceStartYear;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What every kind's form edits (#1410, #1411), each kind's resource adding its own details. Its PATCH reads it first
 * and merges the body in (#1327), so a field left out keeps what is stored, and one sent as null clears it.
 */
abstract class MyServiceForm
{
    public const string OFFER_UNIT_INVALID = 'music_all_eb832ebf-bf8b-4899-be88-eb5b80608707';
    public const string PLACE_TYPE_NOT_OFFERED = 'music_all_f8b58ae4-e96e-4089-97e9-f4436e99bfc8';
    private const int MAX_PLACES = 3;
    private const int MAX_OFFERS = 12;
    private const int MAX_STYLES = 10;

    #[ApiProperty(identifier: true, writable: false)]
    public string $id;

    /** A ServiceStatus value: publishing goes through « Mes services ». */
    #[ApiProperty(writable: false)]
    public string $status;

    #[Assert\Length(max: 150)]
    public ?string $headline = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    /** « Depuis 2015 »: its page counts the years from it (#1453). */
    #[ExperienceStartYear]
    public ?int $experienceStartYear = null;

    /** @var array<string> the kind's speciality values, checked by validateKindValues() */
    #[Assert\Unique]
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

    /** @var TeacherProfileMedia[] */
    #[ApiProperty(writable: false, genId: false)]
    public array $media = [];

    /** @var ServiceWorkView[] « Réalisations » (#1407), each written through its own endpoint */
    #[ApiProperty(writable: false, genId: false)]
    public array $works = [];

    abstract public static function kind(): ServiceKind;

    /** The specialities, the offer units and the places its kind has: only a kind given remotely takes remote work. */
    #[Assert\Callback]
    public function validateKindValues(ExecutionContextInterface $context): void
    {
        $kind = static::kind();
        $specialities = array_map(static fn (ServiceSpeciality $speciality): string|int => $speciality->value, $kind->specialities());
        foreach ($this->specialities as $index => $speciality) {
            if (!in_array($speciality, $specialities, true)) {
                $context->buildViolation('service.speciality_invalid')
                    ->atPath(sprintf('specialities[%d]', $index))
                    ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
                    ->addViolation();
            }
        }

        $units = array_map(static fn (OfferUnit $unit): string => $unit->value, $kind->offerUnits());
        foreach ($this->offers as $index => $offer) {
            if ($offer->unit !== null && $offer->unit !== '' && !in_array($offer->unit, $units, true)) {
                $context->buildViolation('service.offer.unit_invalid')
                    ->atPath(sprintf('offers[%d].unit', $index))
                    ->setCode(self::OFFER_UNIT_INVALID)
                    ->addViolation();
            }
        }

        foreach ($this->places as $index => $place) {
            if ($place->type === ServicePlaceType::Remote->value && !$kind->worksRemotely()) {
                $context->buildViolation('service.place.type_not_offered')
                    ->atPath(sprintf('places[%d].type', $index))
                    ->setCode(self::PLACE_TYPE_NOT_OFFERED)
                    ->addViolation();
            }
        }
    }
}
