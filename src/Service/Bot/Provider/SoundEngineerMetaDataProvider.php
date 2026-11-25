<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\SoundEngineeringSpeciality;
use App\Repository\Service\ServiceRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** « Julien Roche, ingénieur du son à Bruxelles » (#1410), with its schema.org Service. */
readonly class SoundEngineerMetaDataProvider implements PageMetaDataProviderInterface
{
    // Its query (`?from=search`) changes nothing.
    private const string PATTERN = '#^/u/([^/?]+)/sound-engineer(?:\?.*)?$#';

    public function __construct(
        private ServiceRepository $serviceRepository,
        private ProfileMeta $profileMeta,
        private TranslatorInterface $translator,
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

        $service = $this->serviceRepository->findPublishedOneByUsernameAndKind($matches[1], ServiceKind::SoundEngineering);
        if (!$service instanceof Service) {
            return $this->profileMeta->missing();
        }

        $user = $service->user;
        if (($withheld = $this->profileMeta->withheld($user)) !== null) {
            return $withheld;
        }

        $city = $service->mainCity();
        $specialities = $this->specialities($service);
        $parameters = ['name' => $this->profileMeta->name($user), 'specialities' => $specialities, 'city' => $city];

        return [
            'title' => $this->translator->trans($city === null ? 'meta.sound_engineer.title' : 'meta.sound_engineer.title_city', $parameters),
            'description' => $this->description($service, $parameters),
            'cover' => $this->profileMeta->picture($user),
            'json_ld' => $this->schema($service, $specialities),
        ];
    }

    /** « mixage, mastering », or the kind's name when none is said. */
    private function specialities(Service $service): string
    {
        $names = [];
        foreach ($service->specialities as $value) {
            $speciality = SoundEngineeringSpeciality::tryFrom($value);
            if ($speciality instanceof SoundEngineeringSpeciality) {
                $names[] = $speciality->trans($this->translator);
            }
        }

        return mb_strtolower($names !== [] ? implode(', ', $names) : ServiceKind::SoundEngineering->trans($this->translator));
    }

    /** @param array<string, string|null> $parameters */
    private function description(Service $service, array $parameters): string
    {
        // A headline is a title: it gets the full stop the next sentence needs.
        $sentences = [$service->headline
            ? preg_replace('/(?<![.!?…])$/u', '.', rtrim($service->headline))
            : $this->translator->trans('meta.sound_engineer.description', $parameters)];
        if (($offer = $service->headlineOffer(OfferUnit::Track)) instanceof ServiceOffer) {
            $sentences[] = $this->translator->trans('meta.sound_engineer.price', [
                'price' => $this->profileMeta->money($offer->price),
                'unit' => $offer->unit->value,
            ]);
        }

        return $this->profileMeta->shorten(implode(' ', $sentences));
    }

    /** @return array<string, mixed> */
    private function schema(Service $service, string $specialities): array
    {
        $offers = array_map(
            fn (ServiceOffer $offer): array => $this->profileMeta->offer($offer->price, (string) $offer->title),
            $service->offersByPrice(),
        );

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'serviceType' => $this->translator->trans('meta.sound_engineer.service', ['specialities' => $specialities]),
            'url' => $this->profileMeta->url($service->kind->pagePath($service->user->username)),
            'description' => $service->headline ?: null,
            'provider' => $this->profileMeta->person($service->user),
            'areaServed' => $this->profileMeta->areaServed($service, $this->translator->trans('meta.sound_engineer.remote')),
            'offers' => $offers ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
