<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Enum\Musician\SkillLevel;
use App\Enum\Teacher\StudentLevel;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileInstrumentFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\Service\ServiceLinkFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TeacherProfilePublicTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    public function test_get_teacher_profile_success(): void
    {
        self::mockTime('2026-06-15T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'teacher_user',
            'email' => 'teacher@test.com',
        ]);

        ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Professeur de guitare expérimenté',
            'experienceStartYear' => 2016,
            'studentLevels' => [StudentLevel::BEGINNER->value, StudentLevel::INTERMEDIATE->value],
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/teacher_user/teacher');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/teacher_user/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'teacher_user',
            'user_id' => $user->id,
            'display_name' => 'teacher_user',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'musician_instruments' => [],
            'musician_bands' => [],
            'description' => 'Professeur de guitare expérimenté',
            'years_of_experience' => 10,
            'experience_start_year' => 2016,
            'student_levels' => ['beginner', 'intermediate'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-01-15T10:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_with_instruments_and_styles(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'music_teacher',
            'email' => 'music_teacher@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();

        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare rock et pop',
            'studentLevels' => [StudentLevel::BEGINNER->value],
            'creationDatetime' => new \DateTimeImmutable('2024-02-01T14:00:00+00:00'),
        ]);

        ServiceInstrumentFactory::new()->create([
            'service' => $teacherProfile,
            'instrument' => $guitar,
        ]);

        $teacherProfile->addStyle($rock);
        $teacherProfile->addStyle($pop);
        \Zenstruck\Foundry\Persistence\save($teacherProfile);

        $this->client->request('GET', '/api/user/profile/music_teacher/teacher');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/music_teacher/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'music_teacher',
            'user_id' => $user->id,
            'display_name' => 'music_teacher',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'musician_instruments' => [],
            'musician_bands' => [],
            'description' => 'Cours de guitare rock et pop',
            'student_levels' => ['beginner'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                [
                    '@type' => 'TeacherProfileInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitare',
                ],
            ],
            'styles' => [
                [
                    '@type' => 'TeacherProfileStyle',
                    'id' => $rock->id,
                    'name' => 'Rock',
                ],
                [
                    '@type' => 'TeacherProfileStyle',
                    'id' => $pop->id,
                    'name' => 'Pop',
                ],
            ],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-02-01T14:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'minimal_teacher',
            'email' => 'minimal@test.com',
        ]);

        ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'creationDatetime' => new \DateTimeImmutable('2024-03-01T09:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/minimal_teacher/teacher');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/minimal_teacher/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'minimal_teacher',
            'user_id' => $user->id,
            'display_name' => 'minimal_teacher',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'musician_instruments' => [],
            'musician_bands' => [],
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-03-01T09:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_with_social_links(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'social_teacher',
            'email' => 'social_teacher@test.com',
        ]);

        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Professeur avec réseaux sociaux',
            'creationDatetime' => new \DateTimeImmutable('2024-04-01T10:00:00+00:00'),
        ]);

        ServiceLinkFactory::new()->asYoutube()->create([
            'service' => $teacherProfile,
        ]);
        ServiceLinkFactory::new()->asInstagram()->create([
            'service' => $teacherProfile,
        ]);

        $this->client->request('GET', '/api/user/profile/social_teacher/teacher');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/social_teacher/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'social_teacher',
            'user_id' => $user->id,
            'display_name' => 'social_teacher',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'musician_instruments' => [],
            'musician_bands' => [],
            'description' => 'Professeur avec réseaux sociaux',
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [
                [
                    '@type' => 'TeacherProfileSocialLink',
                    'platform' => 'youtube',
                    'url' => 'https://www.youtube.com/@teacher',
                ],
                [
                    '@type' => 'TeacherProfileSocialLink',
                    'platform' => 'instagram',
                    'url' => 'https://www.instagram.com/teacher',
                ],
            ],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-04-01T10:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_names_the_musician_behind_the_teacher(): void
    {
        self::mockTime('2026-06-15T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'teacher_musician',
            'email' => 'teacher_musician@test.com',
        ]);
        $user->profile->displayName = 'Julien Roche';
        \Zenstruck\Foundry\Persistence\save($user);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'styles' => [],
            'bands' => [
                ['name' => 'Les Anciens', 'style_id' => null, 'role' => null, 'current' => false, 'url' => null],
                ['name' => 'Ardent', 'style_id' => $rock->id, 'role' => 'Guitare', 'current' => true, 'url' => 'https://ardent.bandcamp.com'],
            ],
        ]);
        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::PROFESSIONAL,
        ]);
        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'experienceStartYear' => 2014,
            'studentLevels' => [StudentLevel::BEGINNER->value],
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/teacher_musician/teacher');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/teacher_musician/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'teacher_musician',
            'user_id' => $user->id,
            'display_name' => 'Julien Roche',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'published',
            'musician_instruments' => [
                [
                    '@type' => 'PublicMusicianProfileInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'professional',
                    'skill_level_label' => 'Professionnel',
                ],
            ],
            'musician_bands' => [
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Ardent',
                    'style_id' => $rock->id,
                    'style_name' => 'Rock',
                    'role' => 'Guitare',
                    'current' => true,
                    'url' => 'https://ardent.bandcamp.com',
                ],
            ],
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'experience_start_year' => 2014,
            'student_levels' => ['beginner'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-01-15T10:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_user_not_found(): void
    {
        $this->client->request('GET', '/api/user/profile/nonexistent_user/teacher');
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

    public function test_get_teacher_profile_no_profile(): void
    {
        UserFactory::new()->asBaseUser()->create([
            'username' => 'no_teacher_profile',
            'email' => 'no_teacher@test.com',
        ]);

        $this->client->request('GET', '/api/user/profile/no_teacher_profile/teacher');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil professeur non trouvé',
            'description' => 'Profil professeur non trouvé',
        ]);
    }

    public function test_get_teacher_profile_private(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'private_teacher',
            'email' => 'private_teacher@test.com',
        ]);
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->request('GET', '/api/user/profile/private_teacher/teacher');
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

    public function test_get_teacher_profile_private_is_not_found_for_another_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'private_teacher', 'email' => 'private_teacher@test.com']);
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->teaching()->create(['user' => $user]);
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'viewer', 'email' => 'viewer@test.com']);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/private_teacher/teacher');
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

    /** Its owner still sees a private lessons page, to edit it there (#1317). */
    public function test_get_teacher_profile_private_is_shown_to_its_owner(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'private_teacher',
            'email' => 'private_teacher@test.com',
        ]);
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/profile/private_teacher/teacher');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/private_teacher/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'private_teacher',
            'user_id' => $user->id,
            'display_name' => 'private_teacher',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => false,
            'status' => 'published',
            'musician_instruments' => [],
            'musician_bands' => [],
            'description' => 'Cours de guitare',
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-01-15T10:00:00+00:00',
        ]);
    }

    /** A private member's pages say « privé » whether or not they exist, so nothing about them leaks. */
    public function test_get_teacher_profile_private_without_a_teacher_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'private_no_teacher',
            'email' => 'private_no_teacher@test.com',
        ]);
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->request('GET', '/api/user/profile/private_no_teacher/teacher');
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
}
