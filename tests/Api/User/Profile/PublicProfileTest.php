<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Enum\Musician\AvailabilityStatus;
use App\Enum\Musician\SkillLevel;
use App\Enum\Teacher\SessionDuration;
use App\Enum\SocialPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileInstrumentFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserSocialLinkFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class PublicProfileTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_public_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'publicuser',
            'email' => 'publicuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->bio = 'Test bio content';
        $profile->location = 'Paris, France';
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->request('GET', '/api/user/profile/publicuser');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/publicuser',
            '@type' => 'PublicProfile',
            'username' => 'publicuser',
            'user_id' => $user->id,
            'bio' => 'Test bio content',
            'location' => 'Paris, France',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
        ]);
    }

    public function test_get_public_profile_with_social_links(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'socialuser',
            'email' => 'socialuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->bio = 'Musician bio';
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        UserSocialLinkFactory::new()->create([
            'profile' => $profile,
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@socialuser',
        ]);

        $this->client->request('GET', '/api/user/profile/socialuser');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/socialuser',
            '@type' => 'PublicProfile',
            'username' => 'socialuser',
            'user_id' => $user->id,
            'bio' => 'Musician bio',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [
                [
                    '@type' => 'PublicProfileSocialLink',
                    'platform' => 'youtube',
                    'platform_label' => 'YouTube',
                    'url' => 'https://www.youtube.com/@socialuser',
                ],
            ],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
        ]);
    }

    public function test_get_public_profile_with_musician_announces(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musician',
            'email' => 'musician@test.com',
        ]);
        $profile = $user->profile;
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $instrument = InstrumentFactory::new()->asGuitar()->create();
        $style = StyleFactory::new()->create(['name' => 'Rock']);

        $announce = MusicianAnnounceFactory::new()->create([
            'author' => $user,
            'instrument' => $instrument,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'locationName' => 'Paris',
            'styles' => [$style],
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-15T10:30:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/musician');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/musician',
            '@type' => 'PublicProfile',
            'username' => 'musician',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [
                [
                    '@type' => 'PublicProfileAnnounce',
                    'id' => $announce->id,
                    'creation_datetime' => '2024-01-15T10:30:00+00:00',
                    'type' => MusicianAnnounce::TYPE_MUSICIAN,
                    'instrument_name' => 'Guitariste',
                    'location_name' => 'Paris',
                    'styles' => ['Rock'],
                ],
            ],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
        ]);
    }

    /** The left column says what the member does (#1317), from the profile alone: no musician or teacher page view. */
    public function test_get_public_profile_says_what_the_member_does(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'julien', 'email' => 'julien@test.com']);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::OPEN_TO_COLLABORATIONS,
            'styles' => [$rock],
        ]);
        // Added weakest first: the profile reads them strongest first.
        MusicianProfileInstrumentFactory::new()->create(['musicianProfile' => $musicianProfile, 'instrument' => $drum, 'skillLevel' => SkillLevel::BEGINNER]);
        MusicianProfileInstrumentFactory::new()->create(['musicianProfile' => $musicianProfile, 'instrument' => $guitar, 'skillLevel' => SkillLevel::PROFESSIONAL]);

        $teacherProfile = TeacherProfileFactory::new()->create(['user' => $user, 'offersTrial' => true, 'trialPrice' => 0]);
        TeacherProfileInstrumentFactory::new()->create(['teacherProfile' => $teacherProfile, 'instrument' => $guitar]);
        // Added longest first: the page reads them shortest first.
        foreach ([[SessionDuration::ONE_HOUR, 4500], [SessionDuration::THIRTY_MINUTES, 2500]] as [$duration, $price]) {
            $pricing = new TeacherProfilePricing();
            $pricing->duration = $duration;
            $pricing->price = $price;
            $teacherProfile->addPricing($pricing);
        }
        \Zenstruck\Foundry\Persistence\save($teacherProfile);
        $user->musicianProfile = $musicianProfile;
        $user->teacherProfile = $teacherProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $this->client->request('GET', '/api/user/profile/julien');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/julien',
            '@type' => 'PublicProfile',
            'username' => 'julien',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => true,
            'has_teacher_profile' => true,
            'musician' => [
                '@type' => 'PublicProfileMusician',
                'availability_status' => 'open_to_collaborations',
                'availability_status_label' => 'Ouvert aux collaborations',
                'instruments' => [
                    [
                        '@type' => 'PublicMusicianProfileInstrument',
                        'instrument_id' => $guitar->id,
                        'instrument_name' => 'Guitariste',
                        'skill_level' => 'professional',
                        'skill_level_label' => 'Professionnel',
                    ],
                    [
                        '@type' => 'PublicMusicianProfileInstrument',
                        'instrument_id' => $drum->id,
                        'instrument_name' => 'Batteur',
                        'skill_level' => 'beginner',
                        'skill_level_label' => 'Débutant',
                    ],
                ],
                'styles' => [
                    ['@type' => 'PublicMusicianProfileStyle', 'id' => $rock->id, 'name' => 'Rock'],
                ],
            ],
            'teacher' => [
                '@type' => 'PublicProfileTeacher',
                'instruments' => [
                    ['@type' => 'TeacherProfileInstrument', 'instrument_id' => $guitar->id, 'instrument_name' => 'Guitare'],
                ],
                'offers_trial' => true,
                'trial_price' => 0,
                'pricing' => [
                    ['@type' => 'TeacherProfilePricing', 'duration' => '30min', 'price' => 2500],
                    ['@type' => 'TeacherProfilePricing', 'duration' => '1h', 'price' => 4500],
                ],
            ],
        ]);
    }

    public function test_get_public_profile_not_found(): void
    {
        $this->client->request('GET', '/api/user/profile/nonexistentuser');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil non trouvé',
            'description' => 'Profil non trouvé',
        ]);
    }

    public function test_get_public_profile_private_returns_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'privateuser',
            'email' => 'privateuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->request('GET', '/api/user/profile/privateuser');
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

    public function test_get_public_profile_private_is_not_found_for_its_owner_too(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'privateowner',
            'email' => 'privateowner@test.com',
        ]);
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/profile/privateowner');
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

    public function test_get_public_profile_with_empty_bio_and_location(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'emptyprofile',
            'email' => 'emptyprofile@test.com',
        ]);
        $profile = $user->profile;
        $profile->bio = null;
        $profile->location = null;
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->request('GET', '/api/user/profile/emptyprofile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/emptyprofile',
            '@type' => 'PublicProfile',
            'username' => 'emptyprofile',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
        ]);
    }
}
