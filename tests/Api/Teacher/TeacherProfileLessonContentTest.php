<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Entity\Teacher\TeacherProfile;
use App\Repository\Teacher\TeacherProfileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** « Ce que tu vas apprendre » and « Morceaux travaillés » on the teacher profile (#1312). */
#[ResetDatabase]
class TeacherProfileLessonContentTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_with_goals_and_songs(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner', 'advanced'],
            'instrument_ids' => [$guitar->id],
            'level_goals' => [
                ['level' => 'advanced', 'title' => 'Trouver ton son', 'points' => ['Effets et réglages', ' ', 'Jouer en live']],
                ['level' => 'beginner', 'title' => '  Jouer ton premier morceau  ', 'points' => ['Les accords ouverts']],
            ],
            'songs' => [
                ['artist' => 'Radiohead', 'title' => 'Weird Fishes'],
                ['artist' => '', 'title' => 'Une berceuse'],
            ],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $profile = $this->profileOf($user->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner', 'advanced'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']],
                ['level' => 'advanced', 'title' => 'Trouver ton son', 'points' => ['Effets et réglages', 'Jouer en live']],
            ],
            'songs' => [
                ['artist' => 'Radiohead', 'title' => 'Weird Fishes'],
                ['artist' => null, 'title' => 'Une berceuse'],
            ],
        ]);
    }

    public function test_an_update_that_leaves_them_out_keeps_them(): void
    {
        [$user, $guitar, $profile] = $this->teacherWithContent();

        // What the Android app sends: the whole profile, without the two new fields.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare, mis à jour',
            'years_of_experience' => 13,
            'student_levels' => ['beginner', 'advanced'],
            'instrument_ids' => [$guitar->id],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profile->id,
            'description' => 'Cours de guitare, mis à jour',
            'years_of_experience' => 13,
            'student_levels' => ['beginner', 'advanced'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']],
                ['level' => 'advanced', 'title' => 'Trouver ton son', 'points' => []],
            ],
            'songs' => [
                ['artist' => 'Radiohead', 'title' => 'Weird Fishes'],
            ],
        ]);
    }

    public function test_an_update_replaces_them_and_an_empty_list_clears_them(): void
    {
        [$user, $guitar, $profile] = $this->teacherWithContent();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner', 'intermediate', 'advanced'],
            'instrument_ids' => [$guitar->id],
            'level_goals' => [
                ['level' => 'intermediate', 'title' => '', 'points' => ['Jouer avec d\'autres']],
                ['level' => 'beginner', 'title' => '', 'points' => ['', ' ']],
            ],
            'songs' => [],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner', 'intermediate', 'advanced'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            // A level with neither a title nor a point is dropped.
            'level_goals' => [
                ['level' => 'intermediate', 'title' => null, 'points' => ['Jouer avec d\'autres']],
            ],
            'songs' => [],
        ]);
    }

    public function test_a_level_no_longer_accepted_hides_its_goal_and_keeps_its_text(): void
    {
        [$user, $guitar, $profile] = $this->teacherWithContent();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner'],
            'instrument_ids' => [$guitar->id],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']],
            ],
            'songs' => [
                ['artist' => 'Radiohead', 'title' => 'Weird Fishes'],
            ],
        ]);
        // Ticking the level again brings its text back.
        $this->assertArrayHasKey('advanced', $this->profileOf($user->id)->levelGoals);
    }

    public function test_a_goal_must_name_an_accepted_level_once(): void
    {
        [$user, $guitar] = $this->teacherWithContent();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner'],
            'instrument_ids' => [$guitar->id],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Un'],
                ['level' => 'advanced', 'title' => 'Deux'],
                ['level' => 'beginner', 'title' => 'Trois'],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/0=music_all_6d1f8a52-7c3e-4b9a-9f0d-2e5b8c4a1f73;1=music_all_b2e47c90-1a5d-4f86-8c3b-9d07e6f2a548',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'level_goals[1].level',
                    'message' => 'Ce niveau ne fait pas partie des niveaux acceptés',
                    'code' => 'music_all_6d1f8a52-7c3e-4b9a-9f0d-2e5b8c4a1f73',
                ],
                [
                    'propertyPath' => 'level_goals[2].level',
                    'message' => 'Ce niveau a déjà son contenu',
                    'code' => 'music_all_b2e47c90-1a5d-4f86-8c3b-9d07e6f2a548',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => "level_goals[1].level: Ce niveau ne fait pas partie des niveaux acceptés\nlevel_goals[2].level: Ce niveau a déjà son contenu",
            'type' => '/validation_errors/0=music_all_6d1f8a52-7c3e-4b9a-9f0d-2e5b8c4a1f73;1=music_all_b2e47c90-1a5d-4f86-8c3b-9d07e6f2a548',
            'description' => "level_goals[1].level: Ce niveau ne fait pas partie des niveaux acceptés\nlevel_goals[2].level: Ce niveau a déjà son contenu",
        ]);
    }

    public function test_the_lists_are_bounded(): void
    {
        [$user, $guitar] = $this->teacherWithContent();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner'],
            'instrument_ids' => [$guitar->id],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Un', 'points' => ['a', 'b', 'c', 'd']],
            ],
            'songs' => [
                ['artist' => 'A', 'title' => ''],
                ...array_fill(0, 8, ['artist' => 'B', 'title' => 'Titre']),
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/0=756b1212-697c-468d-a9ad-50dd783bb169;1=756b1212-697c-468d-a9ad-50dd783bb169;2=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'level_goals[0].points',
                    'message' => 'Au plus 3 points par niveau',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
                [
                    'propertyPath' => 'songs',
                    'message' => 'Au plus 8 morceaux',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
                [
                    'propertyPath' => 'songs[0].title',
                    'message' => 'Le titre du morceau est obligatoire',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => "level_goals[0].points: Au plus 3 points par niveau\nsongs: Au plus 8 morceaux\nsongs[0].title: Le titre du morceau est obligatoire",
            'type' => '/validation_errors/0=756b1212-697c-468d-a9ad-50dd783bb169;1=756b1212-697c-468d-a9ad-50dd783bb169;2=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'description' => "level_goals[0].points: Au plus 3 points par niveau\nsongs: Au plus 8 morceaux\nsongs[0].title: Le titre du morceau est obligatoire",
        ]);
    }

    public function test_create_refuses_over_long_text(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner'],
            'instrument_ids' => [$guitar->id],
            'level_goals' => [
                ['level' => 'beginner', 'title' => str_repeat('a', 81), 'points' => [str_repeat('b', 81)]],
            ],
            'songs' => [
                ['artist' => str_repeat('c', 101), 'title' => str_repeat('d', 101)],
            ],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/0=d94b19cc-114f-4f44-9cc4-4138e80a87b9;1=d94b19cc-114f-4f44-9cc4-4138e80a87b9;2=d94b19cc-114f-4f44-9cc4-4138e80a87b9;3=d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'level_goals[0].title',
                    'message' => 'Le titre fait au plus 80 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
                [
                    'propertyPath' => 'level_goals[0].points[0]',
                    'message' => 'Un point fait au plus 80 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
                [
                    'propertyPath' => 'songs[0].artist',
                    'message' => 'L\'artiste fait au plus 100 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
                [
                    'propertyPath' => 'songs[0].title',
                    'message' => 'Le titre fait au plus 100 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => "level_goals[0].title: Le titre fait au plus 80 caractères\nlevel_goals[0].points[0]: Un point fait au plus 80 caractères\nsongs[0].artist: L'artiste fait au plus 100 caractères\nsongs[0].title: Le titre fait au plus 100 caractères",
            'type' => '/validation_errors/0=d94b19cc-114f-4f44-9cc4-4138e80a87b9;1=d94b19cc-114f-4f44-9cc4-4138e80a87b9;2=d94b19cc-114f-4f44-9cc4-4138e80a87b9;3=d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'description' => "level_goals[0].title: Le titre fait au plus 80 caractères\nlevel_goals[0].points[0]: Un point fait au plus 80 caractères\nsongs[0].artist: L'artiste fait au plus 100 caractères\nsongs[0].title: Le titre fait au plus 100 caractères",
        ]);
        $this->assertNull(self::getContainer()->get(TeacherProfileRepository::class)->findOneBy(['user' => $user->id]));
    }

    public function test_a_point_must_be_text(): void
    {
        [$user, $guitar] = $this->teacherWithContent();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner'],
            'instrument_ids' => [$guitar->id],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Un', 'points' => [1]],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'level_goals[0].points[0]',
                    'message' => 'Cette valeur doit être de type string.',
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'level_goals[0].points[0]: Cette valeur doit être de type string.',
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'description' => 'level_goals[0].points[0]: Cette valeur doit être de type string.',
        ]);
    }

    public function test_the_public_page_shows_them(): void
    {
        [$user, $guitar] = $this->teacherWithContent();

        $this->client->request('GET', '/api/user/profile/' . $user->username . '/teacher');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/' . $user->username . '/teacher',
            '@type' => 'TeacherProfile',
            'username' => $user->username,
            'user_id' => $user->id,
            'display_name' => $user->username,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'musician_instruments' => [],
            'musician_bands' => [],
            'description' => 'Cours de guitare',
            'years_of_experience' => 12,
            'student_levels' => ['beginner', 'advanced'],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['@type' => 'TeacherProfileInstrument', 'instrument_id' => $guitar->id, 'instrument_name' => 'Guitare'],
            ],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']],
                ['level' => 'advanced', 'title' => 'Trouver ton son', 'points' => []],
            ],
            'songs' => [
                ['artist' => 'Radiohead', 'title' => 'Weird Fishes'],
            ],
            'creation_datetime' => '2024-01-15T10:00:00+00:00',
        ]);
    }

    /** @return array{0: \App\Entity\User, 1: \App\Entity\Attribute\Instrument, 2: TeacherProfile} */
    private function teacherWithContent(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $profile = TeacherProfileFactory::createOne([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 12,
            'studentLevels' => ['beginner', 'advanced'],
            'ageGroups' => [],
            'courseTitle' => null,
            'offersTrial' => false,
            'levelGoals' => [
                'advanced' => ['title' => 'Trouver ton son', 'points' => []],
                'beginner' => ['title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']],
            ],
            'songs' => [['artist' => 'Radiohead', 'title' => 'Weird Fishes']],
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);
        TeacherProfileInstrumentFactory::createOne(['teacherProfile' => $profile, 'instrument' => $guitar]);

        return [$user, $guitar, $profile];
    }

    private function profileOf(string $userId): TeacherProfile
    {
        $profile = self::getContainer()->get(TeacherProfileRepository::class)->findOneBy(['user' => $userId]);
        $this->assertInstanceOf(TeacherProfile::class, $profile);

        return $profile;
    }
}
