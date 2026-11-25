<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\SoundEngineering;

use App\Entity\Service\Service;
use App\Entity\Service\SoundEngineeringDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A member edits their own sound engineering service (#1410). */
#[ResetDatabase]
class MySoundEngineeringTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_its_owner_reads_the_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Ta démo mixée',
            'specialities' => ['mastering'],
            'styles' => [$rock],
            'turnaroundDays' => 5,
            'gear' => ['Reaper'],
        ]);
        ServicePlaceFactory::new()->atMyPlace(['address' => 'Rue de Forest 1', 'city' => 'Bruxelles', 'latitude' => '50.8503', 'longitude' => '4.3517'])->create(['service' => $service, 'country' => 'Belgique']);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services/' . $service->id . '/sound-engineering');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MySoundEngineering',
            '@id' => '/api/user/services/' . $service->id . '/sound-engineering',
            '@type' => 'MySoundEngineering',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ta démo mixée',
            'description' => null,
            'years_of_experience' => null,
            'specialities' => ['mastering'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            // Its owner sees the address and the point the page never shows.
            'places' => [[
                '@type' => 'MyServicePlace',
                'type' => 'at_my_place',
                'address' => 'Rue de Forest 1',
                'city' => 'Bruxelles',
                'country' => 'Belgique',
                'latitude' => 50.8503,
                'longitude' => 4.3517,
                'radius' => null,
            ]],
            'offers' => [['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'track', 'title' => "Mixage d'un titre", 'description' => null, 'price' => 8000]],
            'turnaround_days' => 5,
            'revisions_included' => null,
            'formats' => [],
            'gear' => ['Reaper'],
            'media' => [],
            'before_after' => [],
            'works' => [],
        ]);
    }

    public function test_a_patch_writes_every_field(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/sound-engineering', [
            'headline' => '  Ta démo mixée comme un disque  ',
            'description' => 'Huit ans de studio.',
            'years_of_experience' => 8,
            'specialities' => ['mixing', 'mastering'],
            'style_ids' => [$rock->id, '00000000-0000-0000-0000-000000000000'],
            'places' => [
                ['type' => 'at_client_place', 'city' => 'Bruxelles', 'latitude' => 50.85, 'longitude' => 4.35, 'radius' => 30],
                // Only travel has a reach of its own.
                ['type' => 'remote', 'radius' => 50],
            ],
            'offers' => [
                ['unit' => 'flat', 'title' => 'EP de 5 titres', 'description' => 'Mixage et master', 'price' => 45000],
                ['unit' => 'track', 'title' => "Mixage d'un titre", 'price' => 8000],
            ],
            'turnaround_days' => 5,
            'revisions_included' => 2,
            'formats' => ['wav', 'ddp'],
            // A tag of spaces is no tag.
            'gear' => [' Reaper ', '   ', 'UAD Apollo x8'],
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $offers = $this->storedOffers($service->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MySoundEngineering',
            '@id' => '/api/user/services/' . $service->id . '/sound-engineering',
            '@type' => 'MySoundEngineering',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ta démo mixée comme un disque',
            'description' => 'Huit ans de studio.',
            'years_of_experience' => 8,
            'specialities' => ['mixing', 'mastering'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'at_client_place', 'address' => null, 'city' => 'Bruxelles', 'country' => null, 'latitude' => 50.85, 'longitude' => 4.35, 'radius' => 30],
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offers["Mixage d'un titre"], 'unit' => 'track', 'title' => "Mixage d'un titre", 'description' => null, 'price' => 8000],
                ['@type' => 'MyServiceOffer', 'id' => $offers['EP de 5 titres'], 'unit' => 'flat', 'title' => 'EP de 5 titres', 'description' => 'Mixage et master', 'price' => 45000],
            ],
            'turnaround_days' => 5,
            'revisions_included' => 2,
            'formats' => ['wav', 'ddp'],
            'gear' => ['Reaper', 'UAD Apollo x8'],
            'media' => [],
            'before_after' => [],
            'works' => [],
        ]);
    }

    /** The PATCH merges into what is stored (#1327): a field left out keeps its value. */
    public function test_a_patch_that_leaves_fields_out_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'headline' => 'Ta démo mixée',
            'description' => 'Huit ans de studio.',
            'yearsOfExperience' => 8,
            'specialities' => ['mixing'],
            'styles' => [$rock],
            'turnaroundDays' => 5,
            'revisionsIncluded' => 2,
            'formats' => ['wav'],
            'gear' => ['Reaper'],
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/sound-engineering', [
            'years_of_experience' => 9,
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MySoundEngineering',
            '@id' => '/api/user/services/' . $service->id . '/sound-engineering',
            '@type' => 'MySoundEngineering',
            'id' => $service->id,
            'status' => 'published',
            'headline' => 'Ta démo mixée',
            'description' => 'Huit ans de studio.',
            'years_of_experience' => 9,
            'specialities' => ['mixing'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'track', 'title' => "Mixage d'un titre", 'description' => null, 'price' => 8000],
            ],
            'turnaround_days' => 5,
            'revisions_included' => 2,
            'formats' => ['wav'],
            'gear' => ['Reaper'],
            'media' => [],
            'before_after' => [],
            'works' => [],
        ]);
    }

    public function test_a_field_sent_as_null_is_cleared(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'headline' => 'Ta démo mixée', 'turnaroundDays' => 5]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/sound-engineering', [
            'turnaround_days' => null,
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $stored = $this->refreshed($service->id);
        $this->assertSame('Ta démo mixée', $stored->headline);
        $this->assertNull(SoundEngineeringDetails::of($stored)->turnaroundDays);
    }

    public function test_what_the_kind_does_not_allow_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/sound-engineering', [
            'specialities' => ['teaching'],
            'places' => [['type' => 'moon', 'radius' => 900]],
            'offers' => [['unit' => 'lesson', 'title' => 'Un cours', 'price' => 2500], ['unit' => 'track', 'title' => '', 'price' => -1]],
            'formats' => ['flac'],
            'gear' => array_fill(0, SoundEngineeringDetails::MAX_GEAR + 1, 'Reaper'),
            'turnaround_days' => 0,
        ], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        // The kind's values first, then its own details, then what every kind's form has (MyServiceForm).
        $detail = "specialities[0]: Cette spécialité n'existe pas\noffers[0].unit: Cette unité n'existe pas pour ce service\nturnaround_days: Le délai doit être compris entre 1 et 365 jours\nformats[0]: Ce format n'existe pas\ngear: Tu peux indiquer 30 éléments de matériel au plus\nplaces[0].type: Ce lieu n'existe pas\nplaces[0].radius: La distance doit être comprise entre 1 et 500 km\noffers[1].title: Donne un nom à cette formule\noffers[1].price: Le prix doit être compris entre 0 et 100 000 €";
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=8e179f1b-97aa-4560-a02f-2a8b42e49df7;1=music_all_eb832ebf-bf8b-4899-be88-eb5b80608707;2=04b91c99-a946-4221-afc5-e65ebac401eb;3=8e179f1b-97aa-4560-a02f-2a8b42e49df7;4=756b1212-697c-468d-a9ad-50dd783bb169;5=8e179f1b-97aa-4560-a02f-2a8b42e49df7;6=04b91c99-a946-4221-afc5-e65ebac401eb;7=c1051bb4-d103-4f74-8988-acbcafc7fdc3;8=04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'specialities[0]', 'message' => "Cette spécialité n'existe pas", 'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7'],
                ['propertyPath' => 'offers[0].unit', 'message' => "Cette unité n'existe pas pour ce service", 'code' => 'music_all_eb832ebf-bf8b-4899-be88-eb5b80608707'],
                ['propertyPath' => 'turnaround_days', 'message' => 'Le délai doit être compris entre 1 et 365 jours', 'code' => '04b91c99-a946-4221-afc5-e65ebac401eb'],
                ['propertyPath' => 'formats[0]', 'message' => "Ce format n'existe pas", 'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7'],
                ['propertyPath' => 'gear', 'message' => 'Tu peux indiquer 30 éléments de matériel au plus', 'code' => '756b1212-697c-468d-a9ad-50dd783bb169'],
                ['propertyPath' => 'places[0].type', 'message' => "Ce lieu n'existe pas", 'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7'],
                ['propertyPath' => 'places[0].radius', 'message' => 'La distance doit être comprise entre 1 et 500 km', 'code' => '04b91c99-a946-4221-afc5-e65ebac401eb'],
                ['propertyPath' => 'offers[1].title', 'message' => 'Donne un nom à cette formule', 'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3'],
                ['propertyPath' => 'offers[1].price', 'message' => 'Le prix doit être compris entre 0 et 100 000 €', 'code' => '04b91c99-a946-4221-afc5-e65ebac401eb'],
            ],
            'detail' => $detail,
            'type' => '/validation_errors/0=8e179f1b-97aa-4560-a02f-2a8b42e49df7;1=music_all_eb832ebf-bf8b-4899-be88-eb5b80608707;2=04b91c99-a946-4221-afc5-e65ebac401eb;3=8e179f1b-97aa-4560-a02f-2a8b42e49df7;4=756b1212-697c-468d-a9ad-50dd783bb169;5=8e179f1b-97aa-4560-a02f-2a8b42e49df7;6=04b91c99-a946-4221-afc5-e65ebac401eb;7=c1051bb4-d103-4f74-8988-acbcafc7fdc3;8=04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
            'description' => $detail,
        ]);
    }

    public function test_another_members_service_is_not_found(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/user/services/' . $service->id . '/sound-engineering');

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

    /** A kind's form never edits another kind's service. */
    public function test_a_teaching_service_is_not_a_sound_engineering_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/sound-engineering', ['headline' => 'Mixage'], self::PATCH_HEADERS);

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
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->request('GET', '/api/user/services/' . $service->id . '/sound-engineering');

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
