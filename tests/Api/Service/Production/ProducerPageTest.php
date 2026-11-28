<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Production;

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
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

/** A producer's page, `/u/{username}/producer` (#1413). */
#[ResetDatabase]
class ProducerPageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    private const array PARIS = ['city' => 'Paris', 'latitude' => '48.8566', 'longitude' => '2.3522'];

    public function test_a_visitor_reads_the_whole_producer_page(): void
    {
        self::mockTime('2026-06-15T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'sam']);
        $user->profile->bio = 'Beatmaker depuis dix ans.';
        save($user);
        $pop = StyleFactory::new()->asPop()->create();
        $service = ServiceFactory::new()->production()->create([
            'user' => $user,
            'headline' => 'Ton son, de la maquette au master',
            'description' => 'Je compose tes instrus et je réalise ton EP.',
            'experienceStartYear' => 2016,
            'specialities' => ['beatmaking', 'record_production'],
            'styles' => [$pop],
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        ServicePlaceFactory::new()->atMyPlace(['address' => 'Rue Oberkampf 1', ...self::PARIS])->create(['service' => $service]);
        $ep = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 150000, 'title' => 'Réalisation d\'un EP']);
        $beat = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 30000, 'title' => 'Une instru', 'description' => 'Pistes séparées incluses']);
        $media = ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => 'https://soundcloud.com/sam/beat-tape',
            'embedId' => 'sam/beat-tape',
            'title' => 'Beat tape',
        ]);
        $nuit = ServiceWorkFactory::new()->create([
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
        $live = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Live au Trabendo', 'position' => 1]);

        $this->client->request('GET', '/api/user/profile/sam/producer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerPage',
            '@id' => '/api/user/profile/sam/producer',
            '@type' => 'ProducerPage',
            'username' => 'sam',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'sam',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'profile_picture_url' => null,
            'bio' => 'Beatmaker depuis dix ans.',
            'musician_instruments' => [],
            'headline' => 'Ton son, de la maquette au master',
            'description' => 'Je compose tes instrus et je réalise ton EP.',
            'years_of_experience' => 10,
            'experience_start_year' => 2016,
            'specialities' => ['beatmaking', 'record_production'],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $pop->id, 'name' => 'Pop']],
            // The address the owner entered is never shown.
            'places' => [
                ['@type' => 'ServicePlaceView', 'type' => 'remote', 'city' => null, 'radius' => null],
                ['@type' => 'ServicePlaceView', 'type' => 'at_my_place', 'city' => 'Paris', 'radius' => null],
            ],
            // The cheapest first.
            'offers' => [
                ['@type' => 'ServiceOfferView', 'id' => $beat->id, 'unit' => 'track', 'title' => 'Une instru', 'description' => 'Pistes séparées incluses', 'price' => 30000],
                ['@type' => 'ServiceOfferView', 'id' => $ep->id, 'unit' => 'flat', 'title' => 'Réalisation d\'un EP', 'description' => null, 'price' => 150000],
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
            // Credits in the owner's order, each with its embed.
            'works' => [
                [
                    '@type' => 'ServiceWorkView',
                    'id' => $nuit->id,
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
                ],
                ['@type' => 'ServiceWorkView', 'id' => $live->id, 'title' => 'Live au Trabendo', 'credit' => null, 'year' => null, 'link_url' => null, 'media' => null, 'images' => []],
            ],
        ]);
    }

    public function test_a_draft_is_not_found_for_a_visitor(): void
    {
        ServiceFactory::new()->production()->create([
            'user' => UserFactory::new()->asBaseUser()->create(['username' => 'sam']),
            'status' => ServiceStatus::Draft,
        ]);

        $this->client->request('GET', '/api/user/profile/sam/producer');

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
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'sam']);
        $service = ServiceFactory::new()->production()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/profile/sam/producer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerPage',
            '@id' => '/api/user/profile/sam/producer',
            '@type' => 'ProducerPage',
            'username' => 'sam',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'sam',
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
            'media' => [],
            'works' => [],
        ]);
    }

    /** Two services, never one (#1413): a sound engineer has no producer page. */
    public function test_a_sound_engineer_is_not_a_producer_page(): void
    {
        ServiceFactory::new()->soundEngineering()->create(['user' => UserFactory::new()->asBaseUser()->create(['username' => 'julien'])]);

        $this->client->request('GET', '/api/user/profile/julien/producer');

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
