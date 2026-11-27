<?php

declare(strict_types=1);

namespace App\Tests\Api\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\User;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\ServiceStatus;
use App\Repository\Service\ServiceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MyServiceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    public function test_list_my_services(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teaching = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Cours de guitare',
            'description' => 'Du blues au jazz',
        ]);
        ServiceFactory::new()->teaching()->create(['headline' => 'Pas le mien']);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/user/services',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/my_services/' . $teaching->id,
                    '@type' => 'MyService',
                    'id' => $teaching->id,
                    'kind' => 'teaching',
                    'headline' => 'Cours de guitare',
                    'status' => 'draft',
                    'completeness' => 20,
                    'missing_to_publish' => ['places', 'offers'],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** In the member's own order (#1415), whatever order they were added in. */
    public function test_list_my_services_in_their_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teaching = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'headline' => 'Cours de guitare',
            'position' => 1,
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'headline' => 'Mixage et prise de son',
            'position' => 0,
            'creationDatetime' => new \DateTimeImmutable('2025-03-01T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/user/services',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/my_services/' . $mixing->id,
                    '@type' => 'MyService',
                    'id' => $mixing->id,
                    'kind' => 'sound_engineering',
                    'headline' => 'Mixage et prise de son',
                    'status' => 'published',
                    'completeness' => 9,
                    'missing_to_publish' => ['description', 'specialities', 'places', 'offers'],
                ],
                [
                    '@id' => '/api/my_services/' . $teaching->id,
                    '@type' => 'MyService',
                    'id' => $teaching->id,
                    'kind' => 'teaching',
                    'headline' => 'Cours de guitare',
                    'status' => 'published',
                    'completeness' => 10,
                    'missing_to_publish' => ['description', 'places', 'offers'],
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_its_owner_reorders_their_services(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teaching = ServiceFactory::new()->teaching()->create(['user' => $user, 'position' => 0]);
        $mixing = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'position' => 1]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/reorder', ['ids' => [$mixing->id, $teaching->id]], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([(string) $mixing->id => 0, (string) $teaching->id => 1], $this->positions());
    }

    /** Another member's service is unknown here: the order is refused whole, and theirs is left alone. */
    public function test_an_order_naming_another_members_service_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teaching = ServiceFactory::new()->teaching()->create(['user' => $user, 'position' => 0]);
        $theirs = ServiceFactory::new()->soundEngineering()->create(['position' => 3]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/reorder', ['ids' => [$theirs->id, $teaching->id]], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Envoie chaque élément une fois, dans le nouvel ordre',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Envoie chaque élément une fois, dans le nouvel ordre',
        ]);
        $this->assertSame([(string) $teaching->id => 0, (string) $theirs->id => 3], $this->positions());
    }

    /** Every service, each once: a partial order would leave positions nobody chose. */
    public function test_an_order_that_misses_a_service_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teaching = ServiceFactory::new()->teaching()->create(['user' => $user, 'position' => 0]);
        $mixing = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'position' => 1]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/reorder', ['ids' => [$mixing->id]], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Envoie chaque élément une fois, dans le nouvel ordre',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Envoie chaque élément une fois, dans le nouvel ordre',
        ]);
        $this->assertSame([(string) $teaching->id => 0, (string) $mixing->id => 1], $this->positions());
    }

    /** Far more ids than a member can have services: refused before any is looked at. */
    public function test_an_oversized_order_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/reorder', ['ids' => array_map(strval(...), range(1, 21))], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'ids',
                    'message' => 'Cette collection doit contenir 20 éléments ou moins.',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
            ],
            'detail' => 'ids: Cette collection doit contenir 20 éléments ou moins.',
            'description' => 'ids: Cette collection doit contenir 20 éléments ou moins.',
            'type' => '/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            'title' => 'An error occurred',
        ]);
    }

    public function test_reorder_requires_a_sign_in(): void
    {
        $service = ServiceFactory::new()->teaching()->create(['position' => 0]);

        $this->client->jsonRequest('POST', '/api/user/services/reorder', ['ids' => [$service->id]], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_list_my_services_requires_a_sign_in(): void
    {
        $this->client->request('GET', '/api/user/services');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_add_a_service_requires_a_sign_in(): void
    {
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'teaching'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_publish_requires_a_sign_in(): void
    {
        $service = ServiceFactory::new()->teaching()->create(['status' => ServiceStatus::Draft]);

        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
        $this->assertSame(ServiceStatus::Draft, $this->reloadedStatus((string) $service->id));
    }

    public function test_add_a_service_starts_an_empty_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'teaching'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneBy(['user' => $user]);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'teaching',
            'headline' => null,
            'status' => 'draft',
            'completeness' => 0,
            'missing_to_publish' => ['headline', 'description', 'places', 'offers'],
        ]);
    }

    /** A new service goes after the member's others (#1415), wherever they placed them. */
    public function test_a_new_service_goes_after_the_others(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->teaching()->create(['user' => $user, 'position' => 3]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'sound_engineering'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneByUserAndKind($user, ServiceKind::SoundEngineering);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'sound_engineering',
            'headline' => null,
            'status' => 'draft',
            'completeness' => 0,
            'missing_to_publish' => ['headline', 'description', 'specialities', 'places', 'offers'],
        ]);
        $this->assertSame(4, $service->position);
        // A draft has never been online: nothing to put on the wall yet.
        $this->assertNull($service->firstPublicationDatetime);
    }

    public function test_add_a_second_service_of_the_same_kind_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'teaching'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_5b0d6c9e-3f1a-4e27-9c84-7a2e1d3b6f50',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'kind',
                    'message' => 'Tu as déjà un service Professeur',
                    'code' => 'music_all_5b0d6c9e-3f1a-4e27-9c84-7a2e1d3b6f50',
                ],
            ],
            'detail' => 'kind: Tu as déjà un service Professeur',
            'type' => '/validation_errors/music_all_5b0d6c9e-3f1a-4e27-9c84-7a2e1d3b6f50',
            'title' => 'An error occurred',
            'description' => 'kind: Tu as déjà un service Professeur',
        ]);
        $this->assertSame(1, self::getContainer()->get(ServiceRepository::class)->count(['user' => $user]));
    }

    public function test_add_an_unknown_kind_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'juggling'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'kind',
                    'message' => "Ce type de service n'existe pas",
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => "kind: Ce type de service n'existe pas",
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => "kind: Ce type de service n'existe pas",
        ]);
    }

    public function test_delete_my_service(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create(['user' => $user]);
        $serviceId = $service->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $serviceId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull(self::getContainer()->get(ServiceRepository::class)->find($serviceId));
    }

    public function test_delete_another_members_service_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create();
        $serviceId = $service->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $serviceId);

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
        $this->assertNotNull(self::getContainer()->get(ServiceRepository::class)->find($serviceId));
    }

    public function test_publish_a_draft_that_has_what_its_kind_needs(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = $this->publishableTeaching($user, ServiceStatus::Draft);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'teaching',
            'headline' => 'Cours de guitare',
            'status' => 'published',
            'completeness' => 40,
            'missing_to_publish' => [],
        ]);
        $this->assertSame(ServiceStatus::Published, $this->reloadedStatus((string) $service->id));
    }

    /** A sound engineer needs specialities on top of what every kind needs; the rest only counts towards complete (#1410). */
    public function test_add_a_sound_engineering_service_starts_an_empty_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'sound_engineering'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneBy(['user' => $user]);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'sound_engineering',
            'headline' => null,
            'status' => 'draft',
            'completeness' => 0,
            'missing_to_publish' => ['headline', 'description', 'specialities', 'places', 'offers'],
        ]);
    }

    public function test_publish_a_sound_engineering_draft_that_has_what_its_kind_needs(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Ta démo mixée',
            'description' => 'Huit ans de studio.',
            'specialities' => ['mixing'],
            'turnaroundDays' => 5,
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'sound_engineering',
            'headline' => 'Ta démo mixée',
            'status' => 'published',
            // 6 of 10: headline, description, specialities, offers, places, turnaround.
            'completeness' => 55,
            'missing_to_publish' => [],
        ]);
    }

    /** A photographer needs what a sound engineer needs (#1411). */
    public function test_add_a_photography_service_starts_an_empty_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'photography'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneBy(['user' => $user]);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'photography',
            'headline' => null,
            'status' => 'draft',
            'completeness' => 0,
            'missing_to_publish' => ['headline', 'description', 'specialities', 'places', 'offers'],
        ]);
    }

    /** A webmaster needs what a photographer needs (#1412). */
    public function test_add_a_web_service_starts_an_empty_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services', ['kind' => 'web'], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneBy(['user' => $user]);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'web',
            'headline' => null,
            'status' => 'draft',
            'completeness' => 0,
            'missing_to_publish' => ['headline', 'description', 'specialities', 'places', 'offers'],
        ]);
    }

    /** What it delivers counts towards complete, not towards publishing (#1411). */
    public function test_publish_a_video_draft_that_has_what_its_kind_needs(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->video()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Ton clip',
            'description' => 'Six ans de clips.',
            'specialities' => ['clip'],
            'deliveryDays' => 15,
        ]);
        ServicePlaceFactory::new()->atClientPlace(['city' => 'Lyon', 'latitude' => '45.764', 'longitude' => '4.8357'], 100)->create(['service' => $service]);
        ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Event, 'quantity' => null, 'price' => 40000, 'title' => "Captation d'un concert"]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'video',
            'headline' => 'Ton clip',
            'status' => 'published',
            // 6 of 8: headline, description, specialities, offers, places, delivery.
            'completeness' => 75,
            'missing_to_publish' => [],
        ]);
    }

    public function test_publish_an_incomplete_draft_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Cours de guitare',
        ]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 422,
            'type' => '/errors/422',
            'detail' => 'Complète ce service avant de le publier',
            'description' => 'Complète ce service avant de le publier',
        ]);
        $this->assertSame(ServiceStatus::Draft, $this->reloadedStatus((string) $service->id));
    }

    /** A teacher profile online before drafts existed may miss a part: it is never taken down for it. */
    public function test_publish_keeps_an_incomplete_service_already_online(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create(['user' => $user, 'headline' => null]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'teaching',
            'headline' => null,
            'status' => 'published',
            'completeness' => 0,
            'missing_to_publish' => ['headline', 'description', 'places', 'offers'],
        ]);
    }

    public function test_publish_another_members_service_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = $this->publishableTeaching(UserFactory::new()->create(), ServiceStatus::Draft);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/publish');

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
        $this->assertSame(ServiceStatus::Draft, $this->reloadedStatus((string) $service->id));
    }

    public function test_unpublish_my_service(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = $this->publishableTeaching($user, ServiceStatus::Published);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/unpublish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $service->id,
            '@type' => 'MyService',
            'id' => $service->id,
            'kind' => 'teaching',
            'headline' => 'Cours de guitare',
            'status' => 'draft',
            'completeness' => 40,
            'missing_to_publish' => [],
        ]);
        $this->assertSame(ServiceStatus::Draft, $this->reloadedStatus((string) $service->id));
    }

    public function test_unpublish_another_members_service_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = $this->publishableTeaching(UserFactory::new()->create(), ServiceStatus::Published);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/unpublish');

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
        $this->assertSame(ServiceStatus::Published, $this->reloadedStatus((string) $service->id));
    }

    /**
     * The app knows nothing of drafts: the teacher profile it creates goes online, as it always did, which is its first
     * publication, and it goes after the member's other services (#1415).
     */
    public function test_a_teacher_profile_created_from_the_app_is_published(): void
    {
        self::mockTime('2026-11-20T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'position' => 0]);
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitar->id],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneByUserAndKind($user, ServiceKind::Teaching);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame(ServiceStatus::Published, $service->status);
        $this->assertSame('2026-11-20T10:00:00+00:00', $service->firstPublicationDatetime?->format(\DATE_ATOM));
        $this->assertSame(1, $service->position);
    }

    /** Headline, description, a place and an offer: what teaching needs before it can be published. */
    private function publishableTeaching(User $user, ServiceStatus $status): Service
    {
        $service = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'status' => $status,
            'headline' => 'Cours de guitare',
            'description' => 'Du blues au jazz',
        ]);
        $place = new ServicePlace();
        $place->type = ServicePlaceType::Remote;
        $service->addPlace($place);
        $offer = new ServiceOffer();
        $offer->unit = OfferUnit::Lesson;
        $offer->quantity = 60;
        $offer->price = 3000;
        $service->addOffer($offer);
        \Zenstruck\Foundry\Persistence\save($service);

        return $service;
    }

    /** @return array<string, int> each service's position, by id */
    private function positions(): array
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $positions = [];
        foreach ($entityManager->getRepository(Service::class)->findBy([], ['position' => 'ASC']) as $service) {
            $positions[(string) $service->id] = $service->position;
        }

        return $positions;
    }

    private function reloadedStatus(string $serviceId): ServiceStatus
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $service = $entityManager->find(Service::class, $serviceId);
        $this->assertInstanceOf(Service::class, $service);

        return $service->status;
    }
}
