<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\TeacherSearchInstrument;
use App\ApiResource\Service\Search\TeacherSearchResult;
use App\Entity\Attribute\Instrument;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Teacher\StudentLevel;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Translation\ReferenceNameTranslator;

readonly class TeacherSearchResultBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $pictureUrlBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ReferenceNameTranslator $referenceNames,
        private ServiceViewBuilder $serviceViewBuilder,
    ) {
    }

    /** @param float|null $distance in metres */
    public function build(Service $service, ?float $distance, ?Instrument $searched): TeacherSearchResult
    {
        $user = $service->user;
        $result = new TeacherSearchResult();
        $result->username = $user->username;
        $result->name = $user->getDisplayName();
        $result->profilePictureUrl = $this->pictureUrlBuilder->build($user);
        $result->instruments = $this->instruments($service, $searched);
        $result->distance = $distance !== null ? round($distance / 1000, 1) : null;
        $result->online = $service->places->exists(static fn (int $key, ServicePlace $place): bool => $place->type === ServicePlaceType::Remote);

        // A lesson as entered, never a package: a package's price covers several lessons.
        $cheapest = $service->cheapestIn(OfferUnit::Lesson);
        $result->lessonPrice = $cheapest?->price;
        $result->lessonMinutes = $cheapest?->quantity;

        $details = TeachingDetails::of($service);
        $result->freeTrial = $details->hasFreeTrial();
        // In teaching order, whatever order the teacher ticked them in.
        $result->studentLevels = array_values(array_intersect(StudentLevel::values(), $details->studentLevels));
        $result->styles = $this->teacherProfileBuilder->buildStyles($service->styles->toArray());
        $result->media = $this->serviceViewBuilder->firstMedia($service);

        return $result;
    }

    /** @return list<TeacherSearchInstrument> the searched one first, as « Prof de basse et de guitare » reads */
    private function instruments(Service $service, ?Instrument $searched): array
    {
        $instruments = array_map(fn (ServiceInstrument $taught): TeacherSearchInstrument => $this->instrument($taught->instrument), $service->instruments->toArray());
        usort($instruments, static fn (TeacherSearchInstrument $a, TeacherSearchInstrument $b): int => ($b->id === $searched?->id) <=> ($a->id === $searched?->id));

        return $instruments;
    }

    private function instrument(Instrument $instrument): TeacherSearchInstrument
    {
        $dto = new TeacherSearchInstrument();
        $dto->id = (string) $instrument->id;
        $dto->name = $this->referenceNames->instrument($instrument);
        $dto->slug = $instrument->slug;

        return $dto;
    }
}
