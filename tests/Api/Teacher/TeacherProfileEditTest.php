<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Entity\Teacher\TeacherAvailability;
use App\Entity\Teacher\TeacherProfileLocation;
use App\Entity\Teacher\TeacherProfilePackage;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Enum\Teacher\StudentLevel;
use App\Repository\Teacher\TeacherProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TeacherProfileEditTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_teacher_profile(): void
    {
        $teacherProfileRepository = self::getContainer()->get(TeacherProfileRepository::class);
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'new_teacher',
            'email' => 'new_teacher@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        $this->assertNull($teacherProfileRepository->findOneBy(['user' => $user->_real()]));

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Nouveau profil de professeur de guitare',
            'years_of_experience' => 8,
            'student_levels' => ['beginner', 'intermediate'],
            'age_groups' => ['children', 'teenagers'],
            'course_title' => 'Cours de guitare rock',
            'offers_trial' => true,
            'trial_price' => 15,
            'instrument_ids' => [$guitar->getId(), $drum->getId()],
            'style_ids' => [$rock->getId(), $metal->getId()],
            'locations' => [
                ['type' => 'teacher_place', 'address' => '10 rue de la Musique', 'city' => 'Paris', 'country' => 'France', 'latitude' => 48.8566, 'longitude' => 2.3522],
                ['type' => 'online'],
            ],
            'pricing' => [
                ['duration' => '30min', 'price' => 25],
                ['duration' => '1h', 'price' => 45],
            ],
            'availability' => [
                ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '12:00'],
                ['day_of_week' => 'wednesday', 'start_time' => '14:00', 'end_time' => '18:00'],
            ],
            'packages' => [
                ['title' => 'Pack débutant', 'description' => '5 cours pour bien commencer', 'sessions_count' => 5, 'price' => 200],
            ],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        // Decode response to get the actual IDs (order may differ from Doctrine collection)
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $profile = $teacherProfileRepository->findOneBy(['user' => $user->_real()]);
        $this->assertNotNull($profile);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profile->getId(),
            'description' => 'Nouveau profil de professeur de guitare',
            'years_of_experience' => 8,
            'student_levels' => ['beginner', 'intermediate'],
            'age_groups' => ['children', 'teenagers'],
            'course_title' => 'Cours de guitare rock',
            'offers_trial' => true,
            'trial_price' => 15,
            'locations' => [
                [
                    'id' => $responseData['locations'][0]['id'],
                    'type' => 'teacher_place',
                    'address' => '10 rue de la Musique',
                    'city' => 'Paris',
                    'country' => 'France',
                    'latitude' => '48.8566',
                    'longitude' => '2.3522',
                    '@type' => 'TeacherProfileLocation',
                ],
                [
                    'id' => $responseData['locations'][1]['id'],
                    'type' => 'online',
                    '@type' => 'TeacherProfileLocation',
                ],
            ],
            'instruments' => [
                ['instrument_id' => $guitar->getId(), 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
                ['instrument_id' => $drum->getId(), 'instrument_name' => 'Batteur', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [
                ['id' => $rock->getId(), 'name' => 'Rock', '@type' => 'TeacherProfileStyle'],
                ['id' => $metal->getId(), 'name' => 'Metal', '@type' => 'TeacherProfileStyle'],
            ],
            'pricing' => [
                ['id' => $responseData['pricing'][0]['id'], 'duration' => '30min', 'price' => 25, '@type' => 'TeacherProfilePricing'],
                ['id' => $responseData['pricing'][1]['id'], 'duration' => '1h', 'price' => 45, '@type' => 'TeacherProfilePricing'],
            ],
            'availability' => [
                ['id' => $responseData['availability'][0]['id'], 'day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '12:00', '@type' => 'TeacherProfileAvailability'],
                ['id' => $responseData['availability'][1]['id'], 'day_of_week' => 'wednesday', 'start_time' => '14:00', 'end_time' => '18:00', '@type' => 'TeacherProfileAvailability'],
            ],
            'packages' => [
                ['id' => $responseData['packages'][0]['id'], 'title' => 'Pack débutant', 'description' => '5 cours pour bien commencer', 'sessions_count' => 5, 'price' => 200, '@type' => 'TeacherProfilePackage'],
            ],
        ]);
    }

    public function test_create_teacher_profile_not_logged_in(): void
    {
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Test',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_teacher_profile_already_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        TeacherProfileFactory::new()->create(['user' => $user]);

        $guitarId = $guitar->getId();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Tentative de création',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitarId],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_create_teacher_profile_validation_errors_missing_required_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            // Missing required fields: description, yearsOfExperience, instrumentIds
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=ad32d13f-c3d4-423b-909a-857b961eb720;2=bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'description',
                    'message' => 'La présentation est obligatoire',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'years_of_experience',
                    'message' => 'Les années d\'expérience sont obligatoires',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
                [
                    'propertyPath' => 'instrument_ids',
                    'message' => 'Vous devez sélectionner au moins un instrument',
                    'code' => 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'description: La présentation est obligatoire
years_of_experience: Les années d\'expérience sont obligatoires
instrument_ids: Vous devez sélectionner au moins un instrument',
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=ad32d13f-c3d4-423b-909a-857b961eb720;2=bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            'description' => 'description: La présentation est obligatoire
years_of_experience: Les années d\'expérience sont obligatoires
instrument_ids: Vous devez sélectionner au moins un instrument',
        ]);
    }

    public function test_create_teacher_profile_validation_errors_invalid_years_of_experience(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => 80,
            'instrumentIds' => [$guitar->getId()],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/30fbb013-d015-4232-8b3b-8f3be97a7e14',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'years_of_experience',
                    'message' => 'Les années d\'expérience ne peuvent pas dépasser 70 ans',
                    'code' => '30fbb013-d015-4232-8b3b-8f3be97a7e14',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'years_of_experience: Les années d\'expérience ne peuvent pas dépasser 70 ans',
            'type' => '/validation_errors/30fbb013-d015-4232-8b3b-8f3be97a7e14',
            'description' => 'years_of_experience: Les années d\'expérience ne peuvent pas dépasser 70 ans',
        ]);
    }

    public function test_create_teacher_profile_validation_errors_negative_years_of_experience(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => -5,
            'instrumentIds' => [$guitar->getId()],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/ea4e51d1-3342-48bd-87f1-9e672cd90cad',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'years_of_experience',
                    'message' => 'Les années d\'expérience doivent être positives',
                    'code' => 'ea4e51d1-3342-48bd-87f1-9e672cd90cad',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'years_of_experience: Les années d\'expérience doivent être positives',
            'type' => '/validation_errors/ea4e51d1-3342-48bd-87f1-9e672cd90cad',
            'description' => 'years_of_experience: Les années d\'expérience doivent être positives',
        ]);
    }

    public function test_create_teacher_profile_validation_errors_invalid_student_level(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitar->getId()],
            'studentLevels' => ['invalid_level'],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'student_levels[0]',
                    'message' => 'Niveau invalide : "invalid_level"',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'student_levels[0]: Niveau invalide : "invalid_level"',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'description' => 'student_levels[0]: Niveau invalide : "invalid_level"',
        ]);
    }

    public function test_create_teacher_profile_validation_errors_invalid_age_group(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitar->getId()],
            'ageGroups' => ['invalid_age'],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'age_groups[0]',
                    'message' => 'Tranche d\'âge invalide : "invalid_age"',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'age_groups[0]: Tranche d\'âge invalide : "invalid_age"',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'description' => 'age_groups[0]: Tranche d\'âge invalide : "invalid_age"',
        ]);
    }

    public function test_update_teacher_profile(): void
    {
        $teacherProfileRepository = self::getContainer()->get(TeacherProfileRepository::class);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserFactory::new()->asBaseUser()->create();

        // Initial instruments and styles
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        // New instruments and styles for update
        $drum = InstrumentFactory::new()->asDrum()->create();
        $piano = InstrumentFactory::new()->asPiano()->create();
        $metal = StyleFactory::new()->asMetal()->create();
        $jazz = StyleFactory::new()->asJazz()->create();

        // Create initial profile with all data
        $teacherProfile = TeacherProfileFactory::new()->create([
            'user' => $user,
            'description' => 'Ancien profil de professeur',
            'yearsOfExperience' => 3,
            'studentLevels' => [StudentLevel::BEGINNER->value],
            'ageGroups' => ['children'],
            'courseTitle' => 'Cours de guitare',
            'offersTrial' => true,
            'trialPrice' => 10,
        ]);

        // Add initial instrument relation
        TeacherProfileInstrumentFactory::new()->create([
            'teacherProfile' => $teacherProfile,
            'instrument' => $guitar,
        ]);

        // Add initial style
        $teacherProfile->_real()->addStyle($rock->_real());

        // Add initial location
        $initialLocation = new TeacherProfileLocation();
        $initialLocation->setTeacherProfile($teacherProfile->_real());
        $initialLocation->setType(LocationType::TEACHER_PLACE);
        $initialLocation->setAddress('5 rue Initiale');
        $initialLocation->setCity('Lyon');
        $initialLocation->setCountry('France');
        $teacherProfile->_real()->addLocation($initialLocation);

        // Add initial pricing
        $initialPricing = new TeacherProfilePricing();
        $initialPricing->setTeacherProfile($teacherProfile->_real());
        $initialPricing->setDuration(SessionDuration::THIRTY_MINUTES);
        $initialPricing->setPrice(20);
        $teacherProfile->_real()->addPricing($initialPricing);

        // Add initial availability
        $initialAvailability = new TeacherAvailability();
        $initialAvailability->setTeacherProfile($teacherProfile->_real());
        $initialAvailability->setDayOfWeek(DayOfWeek::TUESDAY);
        $initialAvailability->setStartTime(new \DateTimeImmutable('10:00'));
        $initialAvailability->setEndTime(new \DateTimeImmutable('12:00'));
        $teacherProfile->_real()->addAvailability($initialAvailability);

        // Add initial package
        $initialPackage = new TeacherProfilePackage();
        $initialPackage->setTeacherProfile($teacherProfile->_real());
        $initialPackage->setTitle('Pack initial');
        $initialPackage->setDescription('Description initiale');
        $initialPackage->setSessionsCount(3);
        $initialPackage->setPrice(50);
        $teacherProfile->_real()->addPackage($initialPackage);

        $entityManager->flush();

        $profileId = $teacherProfile->getId();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Nouveau profil mis à jour complètement',
            'years_of_experience' => 12,
            'student_levels' => ['intermediate', 'advanced'],
            'age_groups' => ['teenagers', 'adults', 'seniors'],
            'course_title' => 'Cours de batterie et piano jazz',
            'offers_trial' => false,
            'trial_price' => null,
            'instrument_ids' => [$drum->getId(), $piano->getId()],
            'style_ids' => [$metal->getId(), $jazz->getId()],
            'locations' => [
                ['type' => 'student_place', 'address' => '20 avenue Nouvelle', 'city' => 'Marseille', 'country' => 'France', 'latitude' => 43.2965, 'longitude' => 5.3698, 'radius' => 15],
                ['type' => 'online'],
            ],
            'pricing' => [
                ['duration' => '1h', 'price' => 50],
                ['duration' => '1h30', 'price' => 70],
                ['duration' => '2h', 'price' => 90],
            ],
            'availability' => [
                ['day_of_week' => 'thursday', 'start_time' => '08:00', 'end_time' => '11:00'],
                ['day_of_week' => 'friday', 'start_time' => '14:00', 'end_time' => '19:00'],
                ['day_of_week' => 'saturday', 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
            'packages' => [
                ['title' => 'Pack intensif', 'description' => '10 cours pour progresser vite', 'sessions_count' => 10, 'price' => 400],
                ['title' => 'Pack découverte', 'description' => null, 'sessions_count' => 2, 'price' => 80],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        // Decode response to get the actual IDs (order may differ from Doctrine collection)
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profileId,
            'description' => 'Nouveau profil mis à jour complètement',
            'years_of_experience' => 12,
            'student_levels' => ['intermediate', 'advanced'],
            'age_groups' => ['teenagers', 'adults', 'seniors'],
            'course_title' => 'Cours de batterie et piano jazz',
            'offers_trial' => false,
            'locations' => [
                [
                    'id' => $responseData['locations'][0]['id'],
                    'type' => 'student_place',
                    'address' => '20 avenue Nouvelle',
                    'city' => 'Marseille',
                    'country' => 'France',
                    'latitude' => '43.2965',
                    'longitude' => '5.3698',
                    'radius' => 15,
                    '@type' => 'TeacherProfileLocation',
                ],
                [
                    'id' => $responseData['locations'][1]['id'],
                    'type' => 'online',
                    '@type' => 'TeacherProfileLocation',
                ],
            ],
            'instruments' => [
                ['instrument_id' => $drum->getId(), 'instrument_name' => 'Batteur', '@type' => 'TeacherProfileInstrument'],
                ['instrument_id' => $piano->getId(), 'instrument_name' => 'Pianiste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [
                ['id' => $metal->getId(), 'name' => 'Metal', '@type' => 'TeacherProfileStyle'],
                ['id' => $jazz->getId(), 'name' => 'Jazz', '@type' => 'TeacherProfileStyle'],
            ],
            'pricing' => [
                ['id' => $responseData['pricing'][0]['id'], 'duration' => '1h', 'price' => 50, '@type' => 'TeacherProfilePricing'],
                ['id' => $responseData['pricing'][1]['id'], 'duration' => '1h30', 'price' => 70, '@type' => 'TeacherProfilePricing'],
                ['id' => $responseData['pricing'][2]['id'], 'duration' => '2h', 'price' => 90, '@type' => 'TeacherProfilePricing'],
            ],
            'availability' => [
                ['id' => $responseData['availability'][0]['id'], 'day_of_week' => 'thursday', 'start_time' => '08:00', 'end_time' => '11:00', '@type' => 'TeacherProfileAvailability'],
                ['id' => $responseData['availability'][1]['id'], 'day_of_week' => 'friday', 'start_time' => '14:00', 'end_time' => '19:00', '@type' => 'TeacherProfileAvailability'],
                ['id' => $responseData['availability'][2]['id'], 'day_of_week' => 'saturday', 'start_time' => '09:00', 'end_time' => '17:00', '@type' => 'TeacherProfileAvailability'],
            ],
            'packages' => [
                ['id' => $responseData['packages'][0]['id'], 'title' => 'Pack intensif', 'description' => '10 cours pour progresser vite', 'sessions_count' => 10, 'price' => 400, '@type' => 'TeacherProfilePackage'],
                ['id' => $responseData['packages'][1]['id'], 'title' => 'Pack découverte', 'sessions_count' => 2, 'price' => 80, '@type' => 'TeacherProfilePackage'],
            ],
        ]);
    }

    public function test_update_teacher_profile_not_logged_in(): void
    {
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Test',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
