<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Production;

use App\Entity\Service\Service;
use App\Enum\Musician\MediaPlatform;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\Service\ServiceWorkFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A member edits their own production service (#1413). */
#[ResetDatabase]
class MyProductionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_its_owner_reads_the_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $service = ServiceFactory::new()->production()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Ton son, de la maquette au master',
            'specialities' => ['beatmaking', 'topline'],
            'styles' => [$pop],
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 30000, 'title' => 'Une instru']);
        $media = ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => 'https://soundcloud.com/sam/beat-tape',
            'embedId' => 'sam/beat-tape',
            'title' => 'Beat tape',
        ]);
        $work = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Nuit blanche', 'position' => 0]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services/' . $service->id . '/production');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyProduction',
            '@id' => '/api/user/services/' . $service->id . '/production',
            '@type' => 'MyProduction',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ton son, de la maquette au master',
            'description' => null,
            'experience_start_year' => null,
            'specialities' => ['beatmaking', 'topline'],
            'style_ids' => [$pop->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $pop->id, 'name' => 'Pop']],
            'places' => [[
                '@type' => 'MyServicePlace',
                'type' => 'remote',
                'address' => null,
                'city' => null,
                'country' => null,
                'latitude' => null,
                'longitude' => null,
                'radius' => null,
            ]],
            'offers' => [['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'track', 'title' => 'Une instru', 'description' => null, 'price' => 30000]],
            'media' => [[
                '@type' => 'TeacherProfileMedia',
                'id' => $media->id,
                'platform' => 'soundcloud',
                'url' => 'https://soundcloud.com/sam/beat-tape',
                'embed_id' => 'sam/beat-tape',
                'title' => 'Beat tape',
                'thumbnail_url' => null,
                'position' => 0,
            ]],
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $work->id,
                'title' => 'Nuit blanche',
                'credit' => null,
                'year' => null,
                'link_url' => null,
                'media' => null,
                'images' => [],
            ]],
        ]);
    }

    /** Remote work is a producer's own (#1413), next to a studio. */
    public function test_a_patch_writes_every_field(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $service = ServiceFactory::new()->production()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/production', [
            'headline' => '  Ton son, de la maquette au master  ',
            'description' => 'Dix ans de prods.',
            'experience_start_year' => 2016,
            'specialities' => ['arrangement', 'record_production', 'composition'],
            'style_ids' => [$pop->id],
            'places' => [
                ['type' => 'remote'],
                ['type' => 'at_my_place', 'address' => 'Rue Oberkampf 1', 'city' => 'Paris', 'latitude' => 48.85, 'longitude' => 2.35],
            ],
            'offers' => [
                ['unit' => 'flat', 'title' => 'Réalisation d\'un EP', 'description' => 'Cinq titres', 'price' => 150000],
                ['unit' => 'track', 'title' => 'Une instru', 'price' => 30000],
            ],
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $offers = $this->storedOffers($service->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyProduction',
            '@id' => '/api/user/services/' . $service->id . '/production',
            '@type' => 'MyProduction',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ton son, de la maquette au master',
            'description' => 'Dix ans de prods.',
            'experience_start_year' => 2016,
            'specialities' => ['arrangement', 'record_production', 'composition'],
            'style_ids' => [$pop->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $pop->id, 'name' => 'Pop']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
                ['@type' => 'MyServicePlace', 'type' => 'at_my_place', 'address' => 'Rue Oberkampf 1', 'city' => 'Paris', 'country' => null, 'latitude' => 48.85, 'longitude' => 2.35, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offers['Une instru'], 'unit' => 'track', 'title' => 'Une instru', 'description' => null, 'price' => 30000],
                ['@type' => 'MyServiceOffer', 'id' => $offers['Réalisation d\'un EP'], 'unit' => 'flat', 'title' => 'Réalisation d\'un EP', 'description' => 'Cinq titres', 'price' => 150000],
            ],
            'media' => [],
            'works' => [],
        ]);
        $this->assertSame([], $this->refreshed($service->id)->details);
    }

    /** The PATCH merges into what is stored (#1327): a field left out keeps its value. */
    public function test_a_patch_that_leaves_fields_out_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $service = ServiceFactory::new()->production()->create([
            'user' => $user,
            'headline' => 'Ton son, de la maquette au master',
            'description' => 'Dix ans de prods.',
            'experienceStartYear' => 2016,
            'specialities' => ['beatmaking'],
            'styles' => [$pop],
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 30000, 'title' => 'Une instru']);
        // Each written through its own endpoint: the form's PATCH never touches them.
        $media = ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => 'https://soundcloud.com/sam/beat-tape',
            'embedId' => 'sam/beat-tape',
            'title' => 'Beat tape',
        ]);
        $work = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Nuit blanche',
            'credit' => 'Mona Lisa Overdrive, single',
            'year' => 2025,
            'position' => 0,
            'mediaPlatform' => MediaPlatform::SOUNDCLOUD,
            'mediaUrl' => 'https://soundcloud.com/mona-lisa-overdrive/nuit-blanche',
            'mediaEmbedId' => 'mona-lisa-overdrive/nuit-blanche',
            'mediaTitle' => 'Nuit blanche',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/production', [
            'experience_start_year' => 2015,
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyProduction',
            '@id' => '/api/user/services/' . $service->id . '/production',
            '@type' => 'MyProduction',
            'id' => $service->id,
            'status' => 'published',
            'headline' => 'Ton son, de la maquette au master',
            'description' => 'Dix ans de prods.',
            'experience_start_year' => 2015,
            'specialities' => ['beatmaking'],
            'style_ids' => [$pop->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $pop->id, 'name' => 'Pop']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'track', 'title' => 'Une instru', 'description' => null, 'price' => 30000],
            ],
            'media' => [[
                '@type' => 'TeacherProfileMedia',
                'id' => $media->id,
                'platform' => 'soundcloud',
                'url' => 'https://soundcloud.com/sam/beat-tape',
                'embed_id' => 'sam/beat-tape',
                'title' => 'Beat tape',
                'thumbnail_url' => null,
                'position' => 0,
            ]],
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $work->id,
                'title' => 'Nuit blanche',
                'credit' => 'Mona Lisa Overdrive, single',
                'year' => 2025,
                'link_url' => null,
                'media' => [
                    '@type' => 'ServiceWorkMedia',
                    'platform' => 'soundcloud',
                    'url' => 'https://soundcloud.com/mona-lisa-overdrive/nuit-blanche',
                    'embed_id' => 'mona-lisa-overdrive/nuit-blanche',
                    'title' => 'Nuit blanche',
                ],
                'images' => [],
            ]],
        ]);
        $stored = $this->refreshed($service->id);
        $this->assertSame([(string) $media->id], array_values(array_map(static fn ($item): string => (string) $item->id, $stored->media->toArray())));
        $this->assertSame([(string) $work->id], array_values(array_map(static fn ($item): string => (string) $item->id, $stored->works->toArray())));
    }

    /** Mixing stays a sound engineer's (#1413), and an event price a photographer's. */
    public function test_what_the_kind_does_not_allow_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->production()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/production', [
            'specialities' => ['beatmaking', 'mixing'],
            'offers' => [['unit' => 'event', 'title' => 'Un concert', 'price' => 30000]],
        ], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = "specialities[1]: Cette spécialité n'existe pas\noffers[0].unit: Cette unité n'existe pas pour ce service";
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=8e179f1b-97aa-4560-a02f-2a8b42e49df7;1=music_all_eb832ebf-bf8b-4899-be88-eb5b80608707',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'specialities[1]', 'message' => "Cette spécialité n'existe pas", 'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7'],
                ['propertyPath' => 'offers[0].unit', 'message' => "Cette unité n'existe pas pour ce service", 'code' => 'music_all_eb832ebf-bf8b-4899-be88-eb5b80608707'],
            ],
            'detail' => $detail,
            'type' => '/validation_errors/0=8e179f1b-97aa-4560-a02f-2a8b42e49df7;1=music_all_eb832ebf-bf8b-4899-be88-eb5b80608707',
            'title' => 'An error occurred',
            'description' => $detail,
        ]);
        $this->assertSame([], $this->refreshed($service->id)->specialities);
    }

    /** A kind's form never edits another kind's service: a sound engineering one is not a production form. */
    public function test_a_sound_engineering_service_is_not_a_production_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/production', ['headline' => 'Prod'], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Service introuvable',
            'description' => 'Service introuvable',
        ]);
    }

    public function test_another_members_service_is_not_found(): void
    {
        $service = ServiceFactory::new()->production()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/user/services/' . $service->id . '/production');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Service introuvable',
            'description' => 'Service introuvable',
        ]);
    }

    public function test_a_visitor_is_refused(): void
    {
        $service = ServiceFactory::new()->production()->create();

        $this->client->request('GET', '/api/user/services/' . $service->id . '/production');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /** @return array<string, string> the stored offers' ids, by title */
    private function storedOffers(string $serviceId): array
    {
        $ids = [];
        foreach ($this->refreshed($serviceId)->offers as $offer) {
            $ids[(string) $offer->title] = (string) $offer->id;
        }

        return $ids;
    }

    private function refreshed(string $serviceId): Service
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $service = $entityManager->find(Service::class, $serviceId);
        $this->assertInstanceOf(Service::class, $service);

        return $service;
    }
}
