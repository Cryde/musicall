<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Visual;

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

use function Zenstruck\Foundry\Persistence\save;

/** A photographer's page, `/u/{username}/photographer`, and a videographer's, `/u/{username}/videographer` (#1411). */
#[ResetDatabase]
class VisualServicePageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array LYON = ['city' => 'Lyon', 'latitude' => '45.764', 'longitude' => '4.8357'];

    public function test_a_visitor_reads_the_whole_photographer_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'lea']);
        $user->profile->bio = 'Je photographie les concerts depuis dix ans.';
        save($user);
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->photography()->create([
            'user' => $user,
            'headline' => 'Tes concerts en images',
            'description' => 'Fosse photo, backstage, pochettes.',
            'yearsOfExperience' => 10,
            'specialities' => ['concert', 'cover'],
            'styles' => [$rock],
            'deliveryDays' => 7,
            'deliverable' => '40 photos retouchées',
        ]);
        ServicePlaceFactory::new()->atMyPlace(['address' => 'Rue de la Charité 1', ...self::LYON])->create(['service' => $service]);
        ServicePlaceFactory::new()->atClientPlace(self::LYON, 100)->create(['service' => $service]);
        $flat = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 60000, 'title' => 'Séance pochette']);
        $event = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Event, 'quantity' => null, 'price' => 25000, 'title' => 'Un concert', 'description' => "Jusqu'à 3 heures"]);
        $media = ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::VIMEO,
            'url' => 'https://vimeo.com/76979871',
            'embedId' => '76979871',
            'title' => 'Showreel',
        ]);
        $fourviere = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Les Nuits de Fourvière',
            'credit' => 'Mona Lisa Overdrive, live',
            'year' => 2025,
            'position' => 0,
        ]);
        $second = ServiceWorkImageFactory::new()->create(['work' => $fourviere, 'position' => 1]);
        $first = ServiceWorkImageFactory::new()->create(['work' => $fourviere, 'position' => 0]);

        $this->client->request('GET', '/api/user/profile/lea/photographer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PhotographerPage',
            '@id' => '/api/user/profile/lea/photographer',
            '@type' => 'PhotographerPage',
            'username' => 'lea',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'lea',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'profile_picture_url' => null,
            'bio' => 'Je photographie les concerts depuis dix ans.',
            'musician_instruments' => [],
            'headline' => 'Tes concerts en images',
            'description' => 'Fosse photo, backstage, pochettes.',
            'years_of_experience' => 10,
            'specialities' => ['concert', 'cover'],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            // The address the owner entered is never shown.
            'places' => [
                ['@type' => 'ServicePlaceView', 'type' => 'at_my_place', 'city' => 'Lyon', 'radius' => null],
                ['@type' => 'ServicePlaceView', 'type' => 'at_client_place', 'city' => 'Lyon', 'radius' => 100],
            ],
            // The cheapest first.
            'offers' => [
                ['@type' => 'ServiceOfferView', 'id' => $event->id, 'unit' => 'event', 'title' => 'Un concert', 'description' => "Jusqu'à 3 heures", 'price' => 25000],
                ['@type' => 'ServiceOfferView', 'id' => $flat->id, 'unit' => 'flat', 'title' => 'Séance pochette', 'description' => null, 'price' => 60000],
            ],
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
            // In the owner's order, each work's images too: the gallery leads with the first.
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $fourviere->id,
                'title' => 'Les Nuits de Fourvière',
                'credit' => 'Mona Lisa Overdrive, live',
                'year' => 2025,
                'link_url' => null,
                'media' => null,
                'images' => [
                    ['@type' => 'ServiceWorkImageView', 'id' => $first->id, 'url' => '/api/services/' . $service->id . '/works/' . $fourviere->id . '/images/' . $first->id],
                    ['@type' => 'ServiceWorkImageView', 'id' => $second->id, 'url' => '/api/services/' . $service->id . '/works/' . $fourviere->id . '/images/' . $second->id],
                ],
            ]],
        ]);
    }

    public function test_a_visitor_reads_a_videographer_page_with_its_videos(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'tom']);
        $service = ServiceFactory::new()->video()->create([
            'user' => $user,
            'headline' => "Ton clip, de l'idée au montage",
            'specialities' => ['clip'],
            'deliveryDays' => 21,
        ]);
        $clip = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Satellites',
            'credit' => 'Mona Lisa Overdrive, clip',
            'year' => 2024,
            'mediaPlatform' => MediaPlatform::VIMEO,
            'mediaUrl' => 'https://vimeo.com/76979871/8272103f6e',
            'mediaEmbedId' => '76979871/8272103f6e',
            'mediaTitle' => 'Satellites',
        ]);

        $this->client->request('GET', '/api/user/profile/tom/videographer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/VideographerPage',
            '@id' => '/api/user/profile/tom/videographer',
            '@type' => 'VideographerPage',
            'username' => 'tom',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'tom',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'profile_picture_url' => null,
            'bio' => null,
            'musician_instruments' => [],
            'headline' => "Ton clip, de l'idée au montage",
            'description' => null,
            'years_of_experience' => null,
            'specialities' => ['clip'],
            'styles' => [],
            'places' => [],
            'offers' => [],
            'delivery_days' => 21,
            'deliverable' => null,
            'media' => [],
            'works' => [[
                '@type' => 'ServiceWorkView',
                'id' => $clip->id,
                'title' => 'Satellites',
                'credit' => 'Mona Lisa Overdrive, clip',
                'year' => 2024,
                'link_url' => null,
                'media' => [
                    '@type' => 'ServiceWorkMedia',
                    'platform' => 'vimeo',
                    'url' => 'https://vimeo.com/76979871/8272103f6e',
                    'embed_id' => '76979871/8272103f6e',
                    'title' => 'Satellites',
                ],
                'images' => [],
            ]],
        ]);
    }

    public function test_a_draft_is_not_found_for_a_visitor(): void
    {
        ServiceFactory::new()->photography()->create([
            'user' => UserFactory::new()->asBaseUser()->create(['username' => 'lea']),
            'status' => ServiceStatus::Draft,
        ]);

        $this->client->request('GET', '/api/user/profile/lea/photographer');

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

    public function test_its_owner_reads_their_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'tom']);
        $service = ServiceFactory::new()->video()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/profile/tom/videographer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/VideographerPage',
            '@id' => '/api/user/profile/tom/videographer',
            '@type' => 'VideographerPage',
            'username' => 'tom',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'tom',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'draft',
            'profile_picture_url' => null,
            'bio' => null,
            'musician_instruments' => [],
            'headline' => null,
            'description' => null,
            'years_of_experience' => null,
            'specialities' => [],
            'styles' => [],
            'places' => [],
            'offers' => [],
            'delivery_days' => null,
            'deliverable' => null,
            'media' => [],
            'works' => [],
        ]);
    }

    /** The kind is part of the address: a photographer has no videographer page. */
    public function test_another_kind_is_not_a_videographer_page(): void
    {
        ServiceFactory::new()->photography()->create(['user' => UserFactory::new()->asBaseUser()->create(['username' => 'lea'])]);

        $this->client->request('GET', '/api/user/profile/lea/videographer');

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
}
