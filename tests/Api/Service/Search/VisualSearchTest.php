<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Search;

use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Enum\Musician\MediaPlatform;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The photographer and videographer searches (#1411). */
#[ResetDatabase]
class VisualSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array LYON = ['lat' => '45.764', 'lng' => '4.8357'];
    // 4.3 km from Lyon.
    private const array VILLEURBANNE = ['city' => 'Villeurbanne', 'latitude' => '45.7719', 'longitude' => '4.8902'];
    // 50.2 km.
    private const array SAINT_ETIENNE = ['city' => 'Saint-Étienne', 'latitude' => '45.4397', 'longitude' => '4.3872'];

    public function test_a_published_photographer_is_found_with_what_their_card_shows(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $lea = $this->provider(ServiceKind::Photography, 'lea', [
            'headline' => 'Tes concerts en images',
            'specialities' => ['concert', 'cover'],
            'deliveryDays' => 7,
            'deliverable' => '40 photos retouchées',
        ], styles: [$rock]);
        $lea->user->profile->displayName = 'Léa Martin';
        ServicePlaceFactory::new()->atMyPlace(self::VILLEURBANNE)->create(['service' => $lea]);
        // The cheapest, whatever its unit.
        ServiceOfferFactory::new()->create(['service' => $lea, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 60000, 'title' => 'Séance pochette']);
        ServiceOfferFactory::new()->create(['service' => $lea, 'unit' => OfferUnit::Event, 'quantity' => null, 'price' => 25000, 'title' => 'Un concert']);
        ServiceMediaFactory::new()->create(['service' => $lea, 'platform' => MediaPlatform::VIMEO, 'url' => 'https://vimeo.com/76979871', 'embedId' => '76979871', 'title' => 'Showreel']);
        $this->flush();

        $this->client->request('GET', '/api/services/photography');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PhotographerSearchResult',
            '@id' => '/api/services/photography',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/photographer_search_results/lea',
                    '@type' => 'PhotographerSearchResult',
                    'username' => 'lea',
                    'name' => 'Léa Martin',
                    'headline' => 'Tes concerts en images',
                    'specialities' => ['concert', 'cover'],
                    'price' => 25000,
                    'price_unit' => 'event',
                    'delivery_days' => 7,
                    'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
                    'media' => ['@type' => 'TeacherSearchMedia', 'platform' => 'vimeo', 'embed_id' => '76979871', 'title' => 'Showreel'],
                ],
            ],
        ]);
    }

    /** Each kind has its own search: a videographer is never a photographer, nor a draft or a private profile found. */
    public function test_drafts_private_profiles_and_other_kinds_are_never_found(): void
    {
        $this->provider(ServiceKind::Video, 'shown');
        $this->provider(ServiceKind::Video, 'draft')->status = ServiceStatus::Draft;
        $this->provider(ServiceKind::Video, 'private')->user->profile->isPublic = false;
        $this->provider(ServiceKind::Photography, 'photographer');
        $this->flush();

        $this->client->request('GET', '/api/services/video');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/VideographerSearchResult',
            '@id' => '/api/services/video',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card(ServiceKind::Video, 'shown')],
        ]);
    }

    public function test_a_speciality_keeps_its_videographers_and_comes_first_on_their_card(): void
    {
        $this->provider(ServiceKind::Video, 'clipper', ['specialities' => ['clip']]);
        $this->provider(ServiceKind::Video, 'both', ['specialities' => ['clip', 'live_session']], created: '2024-01-02');
        $this->provider(ServiceKind::Video, 'editor', ['specialities' => ['editing']]);

        $this->client->request('GET', '/api/services/video', ['speciality' => 'live_session']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/VideographerSearchResult',
            '@id' => '/api/services/video',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card(ServiceKind::Video, 'both', ['specialities' => ['live_session', 'clip']])],
            'view' => ['@id' => '/api/services/video?speciality=live_session', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_style_keeps_its_photographers(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $this->provider(ServiceKind::Photography, 'rocker', styles: [$rock]);
        $this->provider(ServiceKind::Photography, 'jazzman', styles: [$jazz]);

        $this->client->request('GET', '/api/services/photography', ['style' => $jazz->id]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PhotographerSearchResult',
            '@id' => '/api/services/photography',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card(ServiceKind::Photography, 'jazzman', ['styles' => [['@type' => 'TeacherProfileStyle', 'id' => $jazz->id, 'name' => 'Jazz']]])],
            'view' => ['@id' => '/api/services/photography?style=' . $jazz->id, '@type' => 'PartialCollectionView'],
        ]);
    }

    /** A studio within the radius, or a photographer who travels as far as the searched city. */
    public function test_a_radius_keeps_the_places_in_reach(): void
    {
        ServicePlaceFactory::new()->atMyPlace(self::VILLEURBANNE)->create(['service' => $this->provider(ServiceKind::Photography, 'villeurbanne')]);
        ServicePlaceFactory::new()->atClientPlace(self::SAINT_ETIENNE, 80)->create(['service' => $this->provider(ServiceKind::Photography, 'travels')]);
        ServicePlaceFactory::new()->atMyPlace(self::SAINT_ETIENNE)->create(['service' => $this->provider(ServiceKind::Photography, 'stays')]);

        $this->client->request('GET', '/api/services/photography', ['radius' => '10'] + self::LYON);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PhotographerSearchResult',
            '@id' => '/api/services/photography',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->card(ServiceKind::Photography, 'villeurbanne', ['distance' => 4.3]),
                $this->card(ServiceKind::Photography, 'travels', ['distance' => 50.2]),
            ],
            'view' => ['@id' => '/api/services/photography?lat=45.764&lng=4.8357&radius=10', '@type' => 'PartialCollectionView'],
        ]);
    }

    /** Each kind takes its own specialities: a videographer's is not a photographer's. */
    public function test_another_kinds_speciality_is_refused(): void
    {
        $this->client->request('GET', '/api/services/photography', ['speciality' => 'clip']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'speciality', 'message' => "Cette spécialité n'existe pas", 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "speciality: Cette spécialité n'existe pas",
            'description' => "speciality: Cette spécialité n'existe pas",
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /**
     * A published photography or video service, its details given as DeliveryDetails' own fields.
     *
     * @param array<string, mixed> $details
     * @param list<Style>          $styles
     */
    private function provider(ServiceKind $kind, string $username, array $details = [], array $styles = [], string $created = '2024-01-01'): Service
    {
        $service = ServiceFactory::new()->ofKind($kind)->create([
            'user' => UserFactory::new()->create(['username' => $username]),
            'creationDatetime' => new DateTimeImmutable($created . 'T10:00:00+00:00'),
            ...$details,
        ]);
        foreach ($styles as $style) {
            $service->addStyle($style);
        }
        $this->flush();

        return $service;
    }

    private function flush(): void
    {
        self::getContainer()->get('doctrine')->getManager()->flush();
    }

    /**
     * One result as the search answers it: a member with nothing filled in, but what is given.
     *
     * @param array<string, mixed> $filled
     *
     * @return array<string, mixed>
     */
    private function card(ServiceKind $kind, string $username, array $filled = []): array
    {
        $resource = $kind === ServiceKind::Photography ? 'photographer' : 'videographer';

        return [
            '@id' => '/api/' . $resource . '_search_results/' . $username,
            '@type' => ucfirst($resource) . 'SearchResult',
            'username' => $username,
            'name' => $username,
            'specialities' => [],
            'styles' => [],
            ...$filled,
        ];
    }
}
