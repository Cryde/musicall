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
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\Teacher\TeacherSocialLinkFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TeacherProfilePublicTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_teacher_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'teacher_user',
            'email' => 'teacher@test.com',
        ]);

        TeacherProfileFactory::new()->create([
            'user' => $user,
            'description' => 'Professeur de guitare expérimenté',
            'yearsOfExperience' => 10,
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
            'musician_instruments' => [],
            'description' => 'Professeur de guitare expérimenté',
            'years_of_experience' => 10,
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

        $teacherProfile = TeacherProfileFactory::new()->create([
            'user' => $user,
            'description' => 'Cours de guitare rock et pop',
            'studentLevels' => [StudentLevel::BEGINNER->value],
            'creationDatetime' => new \DateTimeImmutable('2024-02-01T14:00:00+00:00'),
        ]);

        TeacherProfileInstrumentFactory::new()->create([
            'teacherProfile' => $teacherProfile,
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
            'musician_instruments' => [],
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
            'creation_datetime' => '2024-02-01T14:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'minimal_teacher',
            'email' => 'minimal@test.com',
        ]);

        TeacherProfileFactory::new()->create([
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
            'musician_instruments' => [],
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
            'creation_datetime' => '2024-03-01T09:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_with_social_links(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'social_teacher',
            'email' => 'social_teacher@test.com',
        ]);

        $teacherProfile = TeacherProfileFactory::new()->create([
            'user' => $user,
            'description' => 'Professeur avec réseaux sociaux',
            'creationDatetime' => new \DateTimeImmutable('2024-04-01T10:00:00+00:00'),
        ]);

        TeacherSocialLinkFactory::new()->asYoutube()->create([
            'teacherProfile' => $teacherProfile,
        ]);
        TeacherSocialLinkFactory::new()->asInstagram()->create([
            'teacherProfile' => $teacherProfile,
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
            'musician_instruments' => [],
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
            'creation_datetime' => '2024-04-01T10:00:00+00:00',
        ]);
    }

    public function test_get_teacher_profile_names_the_musician_behind_the_teacher(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'teacher_musician',
            'email' => 'teacher_musician@test.com',
        ]);
        $user->profile->displayName = 'Julien Roche';
        \Zenstruck\Foundry\Persistence\save($user);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $musicianProfile = MusicianProfileFactory::new()->create(['user' => $user, 'styles' => []]);
        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::PROFESSIONAL,
        ]);
        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        TeacherProfileFactory::new()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 12,
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
            'musician_instruments' => [
                [
                    '@type' => 'PublicMusicianProfileInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'professional',
                    'skill_level_label' => 'Professionnel',
                ],
            ],
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
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
        TeacherProfileFactory::new()->create(['user' => $user]);

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
