<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use App\Translation\ReferenceNameTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class TeacherProfileMetaDataProvider implements PageMetaDataProviderInterface
{
    // Its query (`?from=search`) changes nothing.
    private const string PATTERN = '#^/u/([^/?]+)/teacher(?:\?.*)?$#';

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
        $city = $teacherProfile->mainCity();
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

    /** @param array<string, string|null> $parameters */
    private function description(Service $teacherProfile, array $parameters): string
    {
        $sentences = [$this->translator->trans('meta.teacher.description', $parameters)];
        if (($cheapest = $teacherProfile->cheapestIn(OfferUnit::Lesson)) instanceof ServiceOffer) {
            $sentences[] = $this->translator->trans('meta.teacher.price', [
                'price' => $this->profileMeta->money($cheapest->price),
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
        $offers = [];
        foreach ($teacherProfile->offersIn(OfferUnit::Lesson) as $lesson) {
            $offers[] = $this->profileMeta->offer($lesson->price, $this->translator->trans('meta.teacher.duration', ['minutes' => (string) $lesson->quantity]));
        }
        $details = TeachingDetails::of($teacherProfile);
        if ($details->offersTrial) {
            $offers[] = $this->profileMeta->offer($details->trialPrice ?? 0, $this->translator->trans('meta.teacher.trial'));
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'serviceType' => $this->translator->trans('meta.teacher.service', ['instruments' => implode(', ', $instruments)]),
            'url' => $this->profileMeta->url($teacherProfile->kind->pagePath($teacherProfile->user->username)),
            'description' => $teacherProfile->headline ?: null,
            'provider' => $this->profileMeta->person($teacherProfile->user),
            'areaServed' => $this->profileMeta->areaServed($teacherProfile, $this->translator->trans('meta.teacher.online')),
            'offers' => $offers ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
