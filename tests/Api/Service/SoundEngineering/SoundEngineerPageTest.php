<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\SoundEngineering;

use App\Entity\Service\ServiceAudio;
use App\Enum\Musician\MediaPlatform;
use App\Enum\Service\BeforeAfterSide;
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

/** A sound engineer's page (#1410), `/u/{username}/sound-engineer`. */
#[ResetDatabase]
class SoundEngineerPageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array BRUSSELS = ['city' => 'Bruxelles', 'latitude' => '50.8503', 'longitude' => '4.3517'];

    public function test_a_visitor_reads_the_whole_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'julien']);
        $user->profile->bio = 'Je mixe des démos le soir.';
        save($user);
        $rock = StyleFactory::new()->asRock()->create();
        $service = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'headline' => 'Ta démo mixée comme un disque',
            'description' => 'Huit ans de studio.',
            'yearsOfExperience' => 8,
            'specialities' => ['mixing', 'recording'],
            'styles' => [$rock],
            'turnaroundDays' => 5,
            'revisionsIncluded' => 2,
            'formats' => ['wav', 'stems'],
            'gear' => ['Reaper', 'Neumann U87'],
        ]);
        ServicePlaceFactory::new()->atMyPlace(['address' => 'Rue de Forest 1', ...self::BRUSSELS])->create(['service' => $service]);
        ServicePlaceFactory::new()->atClientPlace(self::BRUSSELS, 30)->create(['service' => $service]);
        ServicePlaceFactory::new()->remote()->create(['service' => $service]);
        $ep = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 45000, 'title' => 'EP de 5 titres', 'description' => 'Mixage et master']);
        $track = ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre", 'description' => "Jusqu'à 24 pistes"]);
        $media = ServiceMediaFactory::new()->create(['service' => $service, 'title' => 'Jardin Public, live session']);
        $after = $this->storeAudio($service, BeforeAfterSide::After, 'Mixé et masterisé', 'Mona Lisa Overdrive, Satellites');
        $before = $this->storeAudio($service, BeforeAfterSide::Before, 'Prise de répète', null);
        $live = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Live au Botanique', 'position' => 1]);
        $satellites = ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Satellites',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'linkUrl' => 'https://monalisaoverdrive.bandcamp.com/album/satellites',
            'mediaPlatform' => MediaPlatform::YOUTUBE,
            'mediaUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'mediaEmbedId' => 'dQw4w9WgXcQ',
            'mediaTitle' => 'Satellites, the clip',
            'position' => 0,
        ]);
        $back = ServiceWorkImageFactory::new()->create(['work' => $satellites, 'position' => 1]);
        $cover = ServiceWorkImageFactory::new()->create(['work' => $satellites, 'position' => 0]);

        $this->client->request('GET', '/api/user/profile/julien/sound-engineer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerPage',
            '@id' => '/api/user/profile/julien/sound-engineer',
            '@type' => 'SoundEngineerPage',
            'username' => 'julien',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'julien',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'profile_picture_url' => null,
            'bio' => 'Je mixe des démos le soir.',
            'musician_instruments' => [],
            'headline' => 'Ta démo mixée comme un disque',
            'description' => 'Huit ans de studio.',
            'years_of_experience' => 8,
            'specialities' => ['mixing', 'recording'],
            'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
            // The address the owner entered is never shown.
            'places' => [
                ['@type' => 'ServicePlaceView', 'type' => 'at_my_place', 'city' => 'Bruxelles', 'radius' => null],
                ['@type' => 'ServicePlaceView', 'type' => 'at_client_place', 'city' => 'Bruxelles', 'radius' => 30],
                ['@type' => 'ServicePlaceView', 'type' => 'remote', 'city' => null, 'radius' => null],
            ],
            // The cheapest first.
            'offers' => [
                ['@type' => 'ServiceOfferView', 'id' => $track->id, 'unit' => 'track', 'title' => "Mixage d'un titre", 'description' => "Jusqu'à 24 pistes", 'price' => 8000],
                ['@type' => 'ServiceOfferView', 'id' => $ep->id, 'unit' => 'flat', 'title' => 'EP de 5 titres', 'description' => 'Mixage et master', 'price' => 45000],
            ],
            'turnaround_days' => 5,
            'revisions_included' => 2,
            'formats' => ['wav', 'stems'],
            'gear' => ['Reaper', 'Neumann U87'],
            'media' => [[
                '@type' => 'TeacherProfileMedia',
                'id' => $media->id,
                'platform' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'embed_id' => 'dQw4w9WgXcQ',
                'title' => 'Jardin Public, live session',
                'thumbnail_url' => null,
                'position' => 0,
            ]],
            // Before first, whatever order they were sent in.
            'before_after' => [
                ['@type' => 'ServiceAudioView', 'audio_id' => $before->id, 'side' => 'before', 'title' => 'Prise de répète', 'credit' => null, 'duration_seconds' => 42, 'peaks' => [0, 128, 255]],
                ['@type' => 'ServiceAudioView', 'audio_id' => $after->id, 'side' => 'after', 'title' => 'Mixé et masterisé', 'credit' => 'Mona Lisa Overdrive, Satellites', 'duration_seconds' => 42, 'peaks' => [0, 128, 255]],
            ],
            // In the owner's order, each work's images too.
            'works' => [
                [
                    '@type' => 'ServiceWorkView',
                    'id' => $satellites->id,
                    'title' => 'Satellites',
                    'credit' => 'Mona Lisa Overdrive, EP',
                    'year' => 2024,
                    'link_url' => 'https://monalisaoverdrive.bandcamp.com/album/satellites',
                    'media' => [
                        '@type' => 'ServiceWorkMedia',
                        'platform' => 'youtube',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'embed_id' => 'dQw4w9WgXcQ',
                        'title' => 'Satellites, the clip',
                    ],
                    'images' => [
                        ['@type' => 'ServiceWorkImageView', 'id' => $cover->id, 'url' => '/api/services/' . $service->id . '/works/' . $satellites->id . '/images/' . $cover->id],
                        ['@type' => 'ServiceWorkImageView', 'id' => $back->id, 'url' => '/api/services/' . $service->id . '/works/' . $satellites->id . '/images/' . $back->id],
                    ],
                ],
                ['@type' => 'ServiceWorkView', 'id' => $live->id, 'title' => 'Live au Botanique', 'credit' => null, 'year' => null, 'link_url' => null, 'media' => null, 'images' => []],
            ],
        ]);
    }

    public function test_a_draft_is_not_found_for_a_visitor(): void
    {
        ServiceFactory::new()->soundEngineering()->create([
            'user' => UserFactory::new()->asBaseUser()->create(['username' => 'julien']),
            'status' => ServiceStatus::Draft,
        ]);

        $this->client->request('GET', '/api/user/profile/julien/sound-engineer');

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
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'julien']);
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/profile/julien/sound-engineer');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerPage',
            '@id' => '/api/user/profile/julien/sound-engineer',
            '@type' => 'SoundEngineerPage',
            'username' => 'julien',
            'id' => $service->id,
            'user_id' => $user->id,
            'display_name' => 'julien',
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
            'turnaround_days' => null,
            'revisions_included' => null,
            'formats' => [],
            'gear' => [],
            'media' => [],
            'before_after' => [],
            'works' => [],
        ]);
    }

    /** The kind is part of the address: a teacher has no sound engineer page. */
    public function test_another_kind_is_not_a_sound_engineer_page(): void
    {
        ServiceFactory::new()->teaching()->create(['user' => UserFactory::new()->asBaseUser()->create(['username' => 'teacher'])]);

        $this->client->request('GET', '/api/user/profile/teacher/sound-engineer');

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

    public function test_a_private_profile_hides_the_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'julien']);
        $user->profile->isPublic = false;
        save($user);
        ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->request('GET', '/api/user/profile/julien/sound-engineer');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/profile-private',
            'detail' => 'Ce profil est privé',
            'description' => 'Ce profil est privé',
        ]);
    }

    public function test_an_unknown_member_is_not_found(): void
    {
        $this->client->request('GET', '/api/user/profile/nobody/sound-engineer');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Utilisateur non trouvé',
            'description' => 'Utilisateur non trouvé',
        ]);
    }

    /** A stored sound without its file: the page reads only the row. */
    private function storeAudio(\App\Entity\Service\Service $service, BeforeAfterSide $side, ?string $title, ?string $credit): ServiceAudio
    {
        $audio = new ServiceAudio();
        $audio->service = $service;
        $audio->side = $side;
        $audio->title = $title;
        $audio->credit = $credit;
        $audio->durationSeconds = 42;
        $audio->peaks = [0, 128, 255];
        $service->audio->add($audio);
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($audio);
        $entityManager->flush();

        return $audio;
    }
}
