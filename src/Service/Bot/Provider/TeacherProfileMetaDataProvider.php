<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePlaceType;
use App\Repository\Service\ServiceRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use App\Translation\ReferenceNameTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class TeacherProfileMetaDataProvider implements PageMetaDataProviderInterface
{
    // Its query (`?from=search`) changes nothing.
    private const string PATTERN = '#^/u/([^/?]+)/teacher(?:\?.*)?$#';
    private const string CURRENCY = 'EUR';

    public function __construct(
        private ServiceRepository $serviceRepository,
        private ProfileMeta $profileMeta,
        private TranslatorInterface $translator,
        private ReferenceNameTranslator $referenceNameTranslator,
    ) {
    }

    public function supports(string $uri): bool
    {
        return (bool) preg_match(self::PATTERN, $uri);
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match(self::PATTERN, $uri, $matches)) {
            return [];
        }

        $teacherProfile = $this->serviceRepository->findPublishedOneByUsernameAndKind($matches[1], ServiceKind::Teaching);
        if (!$teacherProfile instanceof Service) {
            return $this->profileMeta->missing();
        }

        $user = $teacherProfile->user;
        if (($withheld = $this->profileMeta->withheld($user)) !== null) {
            return $withheld;
        }

        $name = $this->profileMeta->name($user);
        $instruments = $this->instruments($teacherProfile);
        $city = $this->city($teacherProfile);
        $parameters = ['name' => $name, 'instruments' => mb_strtolower(implode(', ', $instruments)), 'city' => $city];

        return [
            'title' => $this->translator->trans($city === null ? 'meta.teacher.title' : 'meta.teacher.title_city', $parameters),
            'description' => $this->description($teacherProfile, $parameters),
            'cover' => $this->profileMeta->picture($user),
            'json_ld' => $this->service($teacherProfile, $instruments),
        ];
    }

    /** @return list<string> */
    private function instruments(Service $teacherProfile): array
    {
        $names = [];
        foreach ($teacherProfile->instruments as $instrument) {
            $names[] = $this->referenceNameTranslator->instrument($instrument->instrument);
        }

        return $names ?: [$this->translator->trans('meta.teacher.music')];
    }

    /** Where the lessons are, for the title: the teacher's own place first, then any place, then the profile's city. */
    private function city(Service $teacherProfile): ?string
    {
        $places = $teacherProfile->places->filter(static fn (ServicePlace $place): bool => (bool) $place->city);
        $own = $places->filter(static fn (ServicePlace $place): bool => $place->type === ServicePlaceType::AtMyPlace)->first();
        $any = $own ?: $places->first();

        return $any ? $any->city : ($teacherProfile->user->profile->location ?: null);
    }

    /** @param array<string, string|null> $parameters */
    private function description(Service $teacherProfile, array $parameters): string
    {
        $sentences = [$this->translator->trans('meta.teacher.description', $parameters)];
        if (($cheapest = $teacherProfile->cheapestIn(OfferUnit::Lesson)) instanceof ServiceOffer) {
            $sentences[] = $this->translator->trans('meta.teacher.price', [
                'price' => $this->money($cheapest->price),
                'minutes' => (string) $cheapest->quantity,
            ]);
        }
        if (TeachingDetails::of($teacherProfile)->hasFreeTrial()) {
            $sentences[] = $this->translator->trans('meta.teacher.free_trial');
        }

        return $this->profileMeta->shorten(implode(' ', $sentences));
    }

    /**
     * @param list<string> $instruments
     *
     * @return array<string, mixed>
     */
    private function service(Service $teacherProfile, array $instruments): array
    {
        $areas = [];
        foreach ($teacherProfile->places as $place) {
            if ($place->type === ServicePlaceType::Remote) {
                $areas[] = $this->translator->trans('meta.teacher.online');
            } elseif ($place->city) {
                $areas[] = $place->city;
            }
        }

        $offers = [];
        foreach ($teacherProfile->offersIn(OfferUnit::Lesson) as $lesson) {
            $offers[] = $this->offer($lesson->price, $this->translator->trans('meta.teacher.duration', ['minutes' => (string) $lesson->quantity]));
        }
        $details = TeachingDetails::of($teacherProfile);
        if ($details->offersTrial) {
            $offers[] = $this->offer($details->trialPrice ?? 0, $this->translator->trans('meta.teacher.trial'));
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'serviceType' => $this->translator->trans('meta.teacher.service', ['instruments' => implode(', ', $instruments)]),
            'url' => $this->profileMeta->url('/u/' . $teacherProfile->user->username . '/teacher'),
            'description' => $teacherProfile->headline ?: null,
            'provider' => $this->profileMeta->person($teacherProfile->user),
            'areaServed' => array_values(array_unique($areas)) ?: null,
            'offers' => $offers ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> */
    private function offer(int $cents, string $name): array
    {
        return ['@type' => 'Offer', 'name' => $name, 'price' => number_format($cents / 100, 2, '.', ''), 'priceCurrency' => self::CURRENCY];
    }

    /** « 25 € » rather than « 25,00 € » when the price is a whole number. */
    private function money(int $cents): string
    {
        $formatter = new \NumberFormatter($this->translator->getLocale(), \NumberFormatter::CURRENCY);
        if ($cents % 100 === 0) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
        }

        return (string) $formatter->formatCurrency($cents / 100, self::CURRENCY);
    }
}
