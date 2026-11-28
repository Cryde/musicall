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
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

/**
 * A photographer's page, `/u/{username}/photographer`, and a videographer's, `/u/{username}/videographer` (#1411); a
 * graphic designer's, `/u/{username}/graphic-designer`, and a webmaster's, `/u/{username}/webmaster` (#1412).
 */
#[ResetDatabase]
class VisualServicePageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    private const array LYON = ['city' => 'Lyon', 'latitude' => '45.764', 'longitude' => '4.8357'];

    public function test_a_visitor_reads_the_whole_photographer_page(): void
    {
        self::mockTime('2026-06-15T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'lea']);
        $user->profile->bio = 'Je photographie les concerts depuis dix ans.';
        save($user);
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->photography()->create([
            'user' => $user,
            'headline' => 'Tes concerts en images',
            'description' => 'Fosse photo, backstage, pochettes.',
            'experienceStartYear' => 2016,
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
            'experience_start_year' => 2016,
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
            'experience_start_year' => null,
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

    /** A graphic designer's works are a gallery too, and remote work is one of their places (#1412). */
    public function test_a_visitor_reads_a_graphic_designer_page_with_remote_work(): void
    {
        self::mockTime('2026-06-15T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'nina']);
        $service = ServiceFactory::new()->graphicDesign()->create([
            'user' => $user,
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'experienceStartYear' => 2018,
            'specialities' => ['cover', 'logo'],
            'deliveryDays' => 10,
            'deliverable' => '3 propositions, fichiers HD',
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        ServicePlaceFactory::new()->atMyPlace(self::LYON)->create(['service' => $service]);
        $flat = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 35000, 'title' => 'Une pochette']);
        $hour = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Hour, 'quantity' => null, 'price' => 4500, 'title' => 'Retouches']);
        $cover = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Satellites',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'position' => 0,
        ]);
        $image = ServiceWorkImageFactory::new()->create(['work' => $cover, 'position' => 0]);

        $this->client->request('GET', '/api/user/profile/nina/graphic-designer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/GraphicDesignerPage',
            '@id' => '/api/user/profile/nina/graphic-designer',
            '@type' => 'GraphicDesignerPage',
            'username' => 'nina',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'nina',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'profile_picture_url' => null,
            'bio' => null,
            'musician_instruments' => [],
            'headline' => 'Ta pochette, du croquis au fichier imprimeur',
            'description' => null,
            'years_of_experience' => 8,
            'experience_start_year' => 2018,
            'specialities' => ['cover', 'logo'],
            'styles' => [],
            'places' => [
                ['@type' => 'ServicePlaceView', 'type' => 'remote', 'city' => null, 'radius' => null],
                ['@type' => 'ServicePlaceView', 'type' => 'at_my_place', 'city' => 'Lyon', 'radius' => null],
            ],
            'offers' => [
                ['@type' => 'ServiceOfferView', 'id' => $hour->id, 'unit' => 'hour', 'title' => 'Retouches', 'description' => null, 'price' => 4500],
                ['@type' => 'ServiceOfferView', 'id' => $flat->id, 'unit' => 'flat', 'title' => 'Une pochette', 'description' => null, 'price' => 35000],
            ],
            'delivery_days' => 10,
            'deliverable' => '3 propositions, fichiers HD',
            'media' => [],
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

    /** A webmaster's works are the sites they made, each its address and its screenshots, in the owner's order (#1412). */
    public function test_a_visitor_reads_a_webmaster_page_with_its_sites(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'marc']);
        $service = ServiceFactory::new()->web()->create([
            'user' => $user,
            'headline' => 'Le site de ton groupe, prêt pour la tournée',
            'specialities' => ['band_site', 'epk'],
            'deliveryDays' => 30,
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $site = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Site de Mona Lisa Overdrive',
            'credit' => 'Mona Lisa Overdrive',
            'year' => 2025,
            'linkUrl' => 'https://monalisaoverdrive.example',
            'position' => 0,
        ]);
        $tour = ServiceWorkImageFactory::new()->create(['work' => $site, 'position' => 1]);
        $home = ServiceWorkImageFactory::new()->create(['work' => $site, 'position' => 0]);
        $epk = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Dossier de presse des Satellites',
            'linkUrl' => 'https://satellites.example/presse',
            'position' => 1,
        ]);

        $this->client->request('GET', '/api/user/profile/marc/webmaster');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/WebmasterPage',
            '@id' => '/api/user/profile/marc/webmaster',
            '@type' => 'WebmasterPage',
            'username' => 'marc',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'marc',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'profile_picture_url' => null,
            'bio' => null,
            'musician_instruments' => [],
            'headline' => 'Le site de ton groupe, prêt pour la tournée',
            'description' => null,
            'years_of_experience' => null,
            'experience_start_year' => null,
            'specialities' => ['band_site', 'epk'],
            'styles' => [],
            'places' => [
                ['@type' => 'ServicePlaceView', 'type' => 'remote', 'city' => null, 'radius' => null],
            ],
            'offers' => [],
            'delivery_days' => 30,
            'deliverable' => null,
            'media' => [],
            'works' => [
                [
                    '@type' => 'ServiceWorkView',
                    'id' => $site->id,
                    'title' => 'Site de Mona Lisa Overdrive',
                    'credit' => 'Mona Lisa Overdrive',
                    'year' => 2025,
                    'link_url' => 'https://monalisaoverdrive.example',
                    'media' => null,
                    'images' => [
                        ['@type' => 'ServiceWorkImageView', 'id' => $home->id, 'url' => '/api/services/' . $service->id . '/works/' . $site->id . '/images/' . $home->id],
                        ['@type' => 'ServiceWorkImageView', 'id' => $tour->id, 'url' => '/api/services/' . $service->id . '/works/' . $site->id . '/images/' . $tour->id],
                    ],
                ],
                [
                    '@type' => 'ServiceWorkView',
                    'id' => $epk->id,
                    'title' => 'Dossier de presse des Satellites',
                    'credit' => null,
                    'year' => null,
                    'link_url' => 'https://satellites.example/presse',
                    'media' => null,
                    'images' => [],
                ],
            ],
        ]);
    }

    /** The kind is part of the address: a graphic designer has no webmaster page. */
    public function test_another_kind_is_not_a_webmaster_page(): void
    {
        ServiceFactory::new()->graphicDesign()->create(['user' => UserFactory::new()->asBaseUser()->create(['username' => 'nina'])]);

        $this->client->request('GET', '/api/user/profile/nina/webmaster');

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
            'experience_start_year' => null,
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
