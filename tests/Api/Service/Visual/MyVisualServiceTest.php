<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Visual;

use App\Entity\Service\DeliveryDetails;
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
use App\Tests\Factory\Service\ServiceWorkImageFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A member edits their own photography or video service (#1411), graphic design or web service (#1412). */
#[ResetDatabase]
class MyVisualServiceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const array LYON = ['city' => 'Lyon', 'latitude' => '45.764', 'longitude' => '4.8357'];

    public function test_its_owner_reads_the_photography_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->photography()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Tes concerts en images',
            'specialities' => ['concert', 'cover'],
            'styles' => [$rock],
            'deliveryDays' => 7,
            'deliverable' => '40 photos retouchées',
        ]);
        ServicePlaceFactory::new()->atClientPlace(self::LYON, 50)->create(['service' => $service, 'country' => 'France']);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Event, 'quantity' => null, 'price' => 25000, 'title' => 'Un concert']);
        $media = ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::VIMEO,
            'url' => 'https://vimeo.com/76979871',
            'embedId' => '76979871',
            'title' => 'Showreel',
        ]);
        $work = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Les Nuits de Fourvière', 'position' => 0]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services/' . $service->id . '/photography');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyPhotography',
            '@id' => '/api/user/services/' . $service->id . '/photography',
            '@type' => 'MyPhotography',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Tes concerts en images',
            'description' => null,
            'years_of_experience' => null,
            'specialities' => ['concert', 'cover'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [[
                '@type' => 'MyServicePlace',
                'type' => 'at_client_place',
                'address' => null,
                'city' => 'Lyon',
                'country' => 'France',
                'latitude' => 45.764,
                'longitude' => 4.8357,
                'radius' => 50,
            ]],
            'offers' => [['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'event', 'title' => 'Un concert', 'description' => null, 'price' => 25000]],
            'delivery_days' => 7,
            'deliverable' => '40 photos retouchées',
            'media' => [[
                '@type' => 'TeacherProfileMedia',
                'id' => $media->id,
                'platform' => 'vimeo',
                'url' => 'https://vimeo.com/76979871',
                'embed_id' => '76979871',
                'title' => 'Showreel',
                'thumbnail_url' => null,
                'position' => 0,
            ]],
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $work->id,
                'title' => 'Les Nuits de Fourvière',
                'credit' => null,
                'year' => null,
                'link_url' => null,
                'media' => null,
                'images' => [],
            ]],
        ]);
    }

    public function test_a_patch_writes_every_field(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->video()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/video', [
            'headline' => '  Ton clip, de l\'idée au montage  ',
            'description' => 'Six ans de clips.',
            'years_of_experience' => 6,
            'specialities' => ['clip', 'live_session'],
            'style_ids' => [$rock->id],
            'places' => [
                ['type' => 'at_my_place', 'address' => 'Rue de la Charité 1', 'city' => 'Lyon', 'latitude' => 45.76, 'longitude' => 4.83],
                ['type' => 'at_client_place', 'city' => 'Lyon', 'latitude' => 45.76, 'longitude' => 4.83, 'radius' => 100],
            ],
            'offers' => [
                ['unit' => 'flat', 'title' => 'Un clip', 'description' => 'Tournage et montage', 'price' => 120000],
                ['unit' => 'event', 'title' => "Captation d'un concert", 'price' => 40000],
            ],
            'delivery_days' => 15,
            // Blank is nothing said.
            'deliverable' => '   ',
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $offers = $this->storedOffers($service->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyVideography',
            '@id' => '/api/user/services/' . $service->id . '/video',
            '@type' => 'MyVideography',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ton clip, de l\'idée au montage',
            'description' => 'Six ans de clips.',
            'years_of_experience' => 6,
            'specialities' => ['clip', 'live_session'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'at_my_place', 'address' => 'Rue de la Charité 1', 'city' => 'Lyon', 'country' => null, 'latitude' => 45.76, 'longitude' => 4.83, 'radius' => null],
                ['@type' => 'MyServicePlace', 'type' => 'at_client_place', 'address' => null, 'city' => 'Lyon', 'country' => null, 'latitude' => 45.76, 'longitude' => 4.83, 'radius' => 100],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offers["Captation d'un concert"], 'unit' => 'event', 'title' => "Captation d'un concert", 'description' => null, 'price' => 40000],
                ['@type' => 'MyServiceOffer', 'id' => $offers['Un clip'], 'unit' => 'flat', 'title' => 'Un clip', 'description' => 'Tournage et montage', 'price' => 120000],
            ],
            'delivery_days' => 15,
            'deliverable' => null,
            'media' => [],
            'works' => [],
        ]);
    }

    /** The PATCH merges into what is stored (#1327): a field left out keeps its value. */
    public function test_a_patch_that_leaves_fields_out_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->photography()->create([
            'user' => $user,
            'headline' => 'Tes concerts en images',
            'description' => 'Dix ans de fosse photo.',
            'yearsOfExperience' => 10,
            'specialities' => ['concert'],
            'styles' => [$rock],
            'deliveryDays' => 7,
            'deliverable' => '40 photos retouchées',
        ]);
        ServicePlaceFactory::new()->atMyPlace(self::LYON)->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Hour, 'quantity' => null, 'price' => 9000, 'title' => 'Une heure de studio']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/photography', [
            'years_of_experience' => 11,
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyPhotography',
            '@id' => '/api/user/services/' . $service->id . '/photography',
            '@type' => 'MyPhotography',
            'id' => $service->id,
            'status' => 'published',
            'headline' => 'Tes concerts en images',
            'description' => 'Dix ans de fosse photo.',
            'years_of_experience' => 11,
            'specialities' => ['concert'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'at_my_place', 'address' => null, 'city' => 'Lyon', 'country' => null, 'latitude' => 45.764, 'longitude' => 4.8357, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'hour', 'title' => 'Une heure de studio', 'description' => null, 'price' => 9000],
            ],
            'delivery_days' => 7,
            'deliverable' => '40 photos retouchées',
            'media' => [],
            'works' => [],
        ]);
    }

    public function test_a_field_sent_as_null_is_cleared(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->video()->create(['user' => $user, 'headline' => 'Ton clip', 'deliveryDays' => 15, 'deliverable' => 'Un clip monté']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/video', [
            'delivery_days' => null,
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $stored = $this->refreshed($service->id);
        $this->assertSame('Ton clip', $stored->headline);
        $this->assertSame(['delivery_days' => null, 'deliverable' => 'Un clip monté'], DeliveryDetails::of($stored)->toArray());
    }

    /** A sound engineer's speciality, a price per track: neither is a photographer's. */
    public function test_what_the_kind_does_not_allow_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->photography()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/photography', [
            'specialities' => ['concert', 'mixing'],
            'offers' => [['unit' => 'track', 'title' => 'Une photo', 'price' => 1000]],
            'delivery_days' => 0,
            'deliverable' => str_repeat('a', DeliveryDetails::MAX_DELIVERABLE_LENGTH + 1),
        ], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = "specialities[1]: Cette spécialité n'existe pas\noffers[0].unit: Cette unité n'existe pas pour ce service\ndelivery_days: Le délai de livraison doit être compris entre 1 et 365 jours\ndeliverable: Cette chaîne est trop longue. Elle doit contenir au maximum 100 caractères.";
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=8e179f1b-97aa-4560-a02f-2a8b42e49df7;1=music_all_eb832ebf-bf8b-4899-be88-eb5b80608707;2=04b91c99-a946-4221-afc5-e65ebac401eb;3=d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'specialities[1]', 'message' => "Cette spécialité n'existe pas", 'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7'],
                ['propertyPath' => 'offers[0].unit', 'message' => "Cette unité n'existe pas pour ce service", 'code' => 'music_all_eb832ebf-bf8b-4899-be88-eb5b80608707'],
                ['propertyPath' => 'delivery_days', 'message' => 'Le délai de livraison doit être compris entre 1 et 365 jours', 'code' => '04b91c99-a946-4221-afc5-e65ebac401eb'],
                ['propertyPath' => 'deliverable', 'message' => 'Cette chaîne est trop longue. Elle doit contenir au maximum 100 caractères.', 'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9'],
            ],
            'detail' => $detail,
            'type' => '/validation_errors/0=8e179f1b-97aa-4560-a02f-2a8b42e49df7;1=music_all_eb832ebf-bf8b-4899-be88-eb5b80608707;2=04b91c99-a946-4221-afc5-e65ebac401eb;3=d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => $detail,
        ]);
    }

    /** A photographer or a videographer comes to the client or works in their studio: remote work is not theirs. */
    public function test_remote_work_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->video()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/video', [
            'places' => [
                ['type' => 'at_client_place', 'city' => 'Lyon', 'latitude' => 45.76, 'longitude' => 4.83, 'radius' => 100],
                ['type' => 'remote'],
            ],
        ], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_f8b58ae4-e96e-4089-97e9-f4436e99bfc8',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'places[1].type', 'message' => "Ce lieu n'existe pas pour ce service", 'code' => 'music_all_f8b58ae4-e96e-4089-97e9-f4436e99bfc8'],
            ],
            'detail' => "places[1].type: Ce lieu n'existe pas pour ce service",
            'type' => '/validation_errors/music_all_f8b58ae4-e96e-4089-97e9-f4436e99bfc8',
            'title' => 'An error occurred',
            'description' => "places[1].type: Ce lieu n'existe pas pour ce service",
        ]);
        $this->assertSame(0, $this->refreshed($service->id)->places->count());
    }

    /** A graphic designer works remotely too, and prices a project rather than an event (#1412). */
    public function test_a_graphic_design_patch_writes_every_field_remote_work_included(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->graphicDesign()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/graphic-design', [
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'description' => 'Pochettes, logos et affiches depuis huit ans.',
            'years_of_experience' => 8,
            'specialities' => ['cover', 'visual_identity'],
            'style_ids' => [$rock->id],
            'places' => [
                ['type' => 'remote'],
                ['type' => 'at_my_place', 'city' => 'Lyon', 'latitude' => 45.76, 'longitude' => 4.83],
            ],
            'offers' => [
                ['unit' => 'flat', 'title' => 'Une pochette', 'description' => 'Trois propositions', 'price' => 35000],
                ['unit' => 'hour', 'title' => 'Retouches', 'price' => 4500],
            ],
            'delivery_days' => 10,
            'deliverable' => '3 propositions, fichiers HD',
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $offers = $this->storedOffers($service->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyGraphicDesign',
            '@id' => '/api/user/services/' . $service->id . '/graphic-design',
            '@type' => 'MyGraphicDesign',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'description' => 'Pochettes, logos et affiches depuis huit ans.',
            'years_of_experience' => 8,
            'specialities' => ['cover', 'visual_identity'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
                ['@type' => 'MyServicePlace', 'type' => 'at_my_place', 'address' => null, 'city' => 'Lyon', 'country' => null, 'latitude' => 45.76, 'longitude' => 4.83, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offers['Retouches'], 'unit' => 'hour', 'title' => 'Retouches', 'description' => null, 'price' => 4500],
                ['@type' => 'MyServiceOffer', 'id' => $offers['Une pochette'], 'unit' => 'flat', 'title' => 'Une pochette', 'description' => 'Trois propositions', 'price' => 35000],
            ],
            'delivery_days' => 10,
            'deliverable' => '3 propositions, fichiers HD',
            'media' => [],
            'works' => [],
        ]);
    }

    /** A graphic designer's form: remote work among its places, Vimeo among its media, its works a gallery (#1412). */
    public function test_its_owner_reads_the_graphic_design_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->graphicDesign()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'specialities' => ['cover', 'logo'],
            'styles' => [$rock],
            'deliveryDays' => 10,
            'deliverable' => '3 propositions, fichiers HD',
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        ServicePlaceFactory::new()->atClientPlace(self::LYON, 30)->create(['service' => $service, 'country' => 'France']);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 35000, 'title' => 'Une pochette']);
        $media = ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::VIMEO,
            'url' => 'https://vimeo.com/76979871',
            'embedId' => '76979871',
            'title' => 'Motion design',
        ]);
        $cover = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Satellites',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'position' => 0,
        ]);
        $image = ServiceWorkImageFactory::new()->create(['work' => $cover, 'position' => 0]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services/' . $service->id . '/graphic-design');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyGraphicDesign',
            '@id' => '/api/user/services/' . $service->id . '/graphic-design',
            '@type' => 'MyGraphicDesign',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'description' => null,
            'years_of_experience' => null,
            'specialities' => ['cover', 'logo'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
                ['@type' => 'MyServicePlace', 'type' => 'at_client_place', 'address' => null, 'city' => 'Lyon', 'country' => 'France', 'latitude' => 45.764, 'longitude' => 4.8357, 'radius' => 30],
            ],
            'offers' => [['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'flat', 'title' => 'Une pochette', 'description' => null, 'price' => 35000]],
            'delivery_days' => 10,
            'deliverable' => '3 propositions, fichiers HD',
            'media' => [[
                '@type' => 'TeacherProfileMedia',
                'id' => $media->id,
                'platform' => 'vimeo',
                'url' => 'https://vimeo.com/76979871',
                'embed_id' => '76979871',
                'title' => 'Motion design',
                'thumbnail_url' => null,
                'position' => 0,
            ]],
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $cover->id,
                'title' => 'Satellites',
                'credit' => 'Mona Lisa Overdrive, EP',
                'year' => 2024,
                'link_url' => null,
                'media' => null,
                'images' => [
                    ['@type' => 'ServiceWorkImageView', 'id' => $image->id, 'url' => '/api/services/' . $service->id . '/works/' . $cover->id . '/images/' . $image->id],
                ],
            ]],
        ]);
    }

    /** The PATCH merges into what is stored (#1327): remote work, the studio, the offers and the styles stay when left out. */
    public function test_a_graphic_design_patch_that_leaves_fields_out_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->graphicDesign()->create([
            'user' => $user,
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'description' => 'Pochettes, logos et affiches depuis huit ans.',
            'yearsOfExperience' => 8,
            'specialities' => ['cover', 'merch'],
            'styles' => [$rock],
            'deliveryDays' => 10,
            'deliverable' => '3 propositions, fichiers HD',
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        ServicePlaceFactory::new()->atMyPlace(self::LYON)->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Hour, 'quantity' => null, 'price' => 4500, 'title' => 'Retouches']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/graphic-design', [
            'delivery_days' => 14,
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyGraphicDesign',
            '@id' => '/api/user/services/' . $service->id . '/graphic-design',
            '@type' => 'MyGraphicDesign',
            'id' => $service->id,
            'status' => 'published',
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'description' => 'Pochettes, logos et affiches depuis huit ans.',
            'years_of_experience' => 8,
            'specialities' => ['cover', 'merch'],
            'style_ids' => [$rock->id],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
                ['@type' => 'MyServicePlace', 'type' => 'at_my_place', 'address' => null, 'city' => 'Lyon', 'country' => null, 'latitude' => 45.764, 'longitude' => 4.8357, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'hour', 'title' => 'Retouches', 'description' => null, 'price' => 4500],
            ],
            'delivery_days' => 14,
            'deliverable' => '3 propositions, fichiers HD',
            'media' => [],
            'works' => [],
        ]);
    }

    /** Each work of a webmaster is a site: its address and its screenshots (#1412). */
    public function test_its_owner_reads_the_web_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->web()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'headline' => 'Le site de ton groupe',
            'specialities' => ['band_site'],
            'deliveryDays' => 30,
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 90000, 'title' => 'Un site de groupe']);
        $site = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Site de Mona Lisa Overdrive',
            'linkUrl' => 'https://monalisaoverdrive.example',
            'position' => 0,
        ]);
        $screenshot = ServiceWorkImageFactory::new()->create(['work' => $site, 'position' => 0]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/services/' . $service->id . '/web');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyWeb',
            '@id' => '/api/user/services/' . $service->id . '/web',
            '@type' => 'MyWeb',
            'id' => $service->id,
            'status' => 'draft',
            'headline' => 'Le site de ton groupe',
            'description' => null,
            'years_of_experience' => null,
            'specialities' => ['band_site'],
            'style_ids' => [],
            'styles' => [],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
            ],
            'offers' => [['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'flat', 'title' => 'Un site de groupe', 'description' => null, 'price' => 90000]],
            'delivery_days' => 30,
            'deliverable' => null,
            'media' => [],
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $site->id,
                'title' => 'Site de Mona Lisa Overdrive',
                'credit' => null,
                'year' => null,
                'link_url' => 'https://monalisaoverdrive.example',
                'media' => null,
                'images' => [
                    ['@type' => 'ServiceWorkImageView', 'id' => $screenshot->id, 'url' => '/api/services/' . $service->id . '/works/' . $site->id . '/images/' . $screenshot->id],
                ],
            ]],
        ]);
    }

    /** The PATCH merges into what is stored (#1327): remote work, the offers and the delivery stay when left out. */
    public function test_a_web_patch_that_leaves_fields_out_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->web()->create([
            'user' => $user,
            'headline' => 'Le site de ton groupe',
            'description' => 'Des sites rapides et simples à tenir à jour.',
            'yearsOfExperience' => 5,
            'specialities' => ['band_site', 'redesign_maintenance'],
            'deliveryDays' => 30,
            'deliverable' => 'Un site en ligne',
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $offer = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Day, 'quantity' => null, 'price' => 40000, 'title' => 'Une journée de refonte']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/web', [
            'headline' => 'Le site de ton groupe, prêt pour la tournée',
        ], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyWeb',
            '@id' => '/api/user/services/' . $service->id . '/web',
            '@type' => 'MyWeb',
            'id' => $service->id,
            'status' => 'published',
            'headline' => 'Le site de ton groupe, prêt pour la tournée',
            'description' => 'Des sites rapides et simples à tenir à jour.',
            'years_of_experience' => 5,
            'specialities' => ['band_site', 'redesign_maintenance'],
            'style_ids' => [],
            'styles' => [],
            'places' => [
                ['@type' => 'MyServicePlace', 'type' => 'remote', 'address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null, 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'MyServiceOffer', 'id' => $offer->id, 'unit' => 'day', 'title' => 'Une journée de refonte', 'description' => null, 'price' => 40000],
            ],
            'delivery_days' => 30,
            'deliverable' => 'Un site en ligne',
            'media' => [],
            'works' => [],
        ]);
    }

    /** A graphic designer's speciality, a price per event: neither is a webmaster's. */
    public function test_what_the_web_kind_does_not_allow_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->web()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/web', [
            'specialities' => ['band_site', 'cover'],
            'offers' => [['unit' => 'event', 'title' => 'Un site', 'price' => 90000]],
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
    }

    /** A kind's form never edits another kind's service, a video one included. */
    public function test_a_video_service_is_not_a_photography_form(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->video()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/photography', ['headline' => 'Photo'], self::PATCH_HEADERS);

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
        $service = ServiceFactory::new()->video()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/user/services/' . $service->id . '/video');

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
        $service = ServiceFactory::new()->photography()->create();

        $this->client->request('GET', '/api/user/services/' . $service->id . '/photography');

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
