<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServiceSpeciality;
use App\Repository\Service\ServiceRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * « Julien Roche, ingénieur du son à Bruxelles » (#1410), « Léa Martin, photographe à Lyon » (#1411), « Nina Roy,
 * graphiste » (#1412), « Sam Diallo, producteur » (#1413): a service page with its schema.org Service. The teacher
 * page has its own (TeacherProfileMetaDataProvider).
 */
readonly class ServicePageMetaDataProvider implements PageMetaDataProviderInterface
{
    // Its query (`?from=search`) changes nothing.
    private const string PATTERN = '#^/u/([^/?]+)/(sound-engineer|photographer|videographer|graphic-designer|webmaster|producer)(?:\?.*)?$#';

    /** Each kind's page, by the last segment of its path, and its words. */
    private const array PAGES = [
        'sound-engineer' => [
            'kind' => ServiceKind::SoundEngineering,
            'title' => 'meta.sound_engineer.title',
            'title_city' => 'meta.sound_engineer.title_city',
            'description' => 'meta.sound_engineer.description',
            'service' => 'meta.sound_engineer.service',
        ],
        'photographer' => [
            'kind' => ServiceKind::Photography,
            'title' => 'meta.photographer.title',
            'title_city' => 'meta.photographer.title_city',
            'description' => 'meta.photographer.description',
            'service' => 'meta.photographer.service',
        ],
        'videographer' => [
            'kind' => ServiceKind::Video,
            'title' => 'meta.videographer.title',
            'title_city' => 'meta.videographer.title_city',
            'description' => 'meta.videographer.description',
            'service' => 'meta.videographer.service',
        ],
        'graphic-designer' => [
            'kind' => ServiceKind::GraphicDesign,
            'title' => 'meta.graphic_designer.title',
            'title_city' => 'meta.graphic_designer.title_city',
            'description' => 'meta.graphic_designer.description',
            'service' => 'meta.graphic_designer.service',
        ],
        'webmaster' => [
            'kind' => ServiceKind::Web,
            'title' => 'meta.webmaster.title',
            'title_city' => 'meta.webmaster.title_city',
            'description' => 'meta.webmaster.description',
            'service' => 'meta.webmaster.service',
        ],
        'producer' => [
            'kind' => ServiceKind::Production,
            'title' => 'meta.producer.title',
            'title_city' => 'meta.producer.title_city',
            'description' => 'meta.producer.description',
            'service' => 'meta.producer.service',
        ],
    ];

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

        $page = self::PAGES[$matches[2]];
        $service = $this->serviceRepository->findPublishedOneByUsernameAndKind($matches[1], $page['kind']);
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
            'title' => $this->translator->trans($city === null ? $page['title'] : $page['title_city'], $parameters),
            'description' => $this->description($service, $page['description'], $parameters),
            'cover' => $this->profileMeta->picture($user),
            'json_ld' => $this->schema($service, $page['service'], $specialities),
        ];
    }

    /** « mixage, mastering », or the kind's name when none is said. */
    private function specialities(Service $service): string
    {
        $names = [];
        foreach ($service->specialities as $value) {
            $speciality = $service->kind->speciality($value);
            if ($speciality instanceof ServiceSpeciality) {
                $names[] = $speciality->trans($this->translator);
            }
        }

        return mb_strtolower($names !== [] ? implode(', ', $names) : $service->kind->trans($this->translator));
    }

    /** @param array<string, string|null> $parameters */
    private function description(Service $service, string $descriptionKey, array $parameters): string
    {
        // A headline is a title: it gets the full stop the next sentence needs.
        $sentences = [$service->headline
            ? preg_replace('/(?<![.!?…])$/u', '.', rtrim($service->headline))
            : $this->translator->trans($descriptionKey, $parameters)];
        if (($offer = $service->leadOffer()) instanceof ServiceOffer) {
            $sentences[] = $this->translator->trans('meta.service.price', [
                'price' => $this->profileMeta->money($offer->price),
                'unit' => $offer->unit->value,
            ]);
        }

        return $this->profileMeta->shorten(implode(' ', $sentences));
    }

    /** @return array<string, mixed> */
    private function schema(Service $service, string $serviceKey, string $specialities): array
    {
        $offers = array_map(
            fn (ServiceOffer $offer): array => $this->profileMeta->offer($offer->price, (string) $offer->title),
            $service->offersByPrice(),
        );

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'serviceType' => $this->translator->trans($serviceKey, ['specialities' => $specialities]),
            'url' => $this->profileMeta->url($service->kind->pagePath($service->user->username)),
            'description' => $service->headline ?: null,
            'provider' => $this->profileMeta->person($service->user),
            'areaServed' => $this->profileMeta->areaServed($service, $this->translator->trans('meta.service.remote')),
            'offers' => $offers ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
