<?php

declare(strict_types=1);

namespace App\Tests\Api\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\User;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\ServiceStatus;
use App\Repository\Service\ServiceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MyServiceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

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

    /** The app knows nothing of drafts: the teacher profile it creates goes online, as it always did. */
    public function test_a_teacher_profile_created_from_the_app_is_published(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitar->id],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $service = self::getContainer()->get(ServiceRepository::class)->findOneBy(['user' => $user]);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame(ServiceStatus::Published, $service->status);
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

    private function reloadedStatus(string $serviceId): ServiceStatus
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $service = $entityManager->find(Service::class, $serviceId);
        $this->assertInstanceOf(Service::class, $service);

        return $service->status;
    }
}
