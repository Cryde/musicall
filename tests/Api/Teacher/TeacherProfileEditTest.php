<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Entity\Service\ServiceAvailability;
use App\Entity\Service\ServiceLink;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\SocialPlatform;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\SessionDuration;
use App\Enum\Teacher\StudentLevel;
use App\Repository\Service\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TeacherProfileEditTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_teacher_profile(): void
    {
        $teacherProfileRepository = self::getContainer()->get(ServiceRepository::class);
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'new_teacher',
            'email' => 'new_teacher@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        $this->assertNull($teacherProfileRepository->findOneBy(['user' => $user]));

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Nouveau profil de professeur de guitare',
            'years_of_experience' => 8,
            'student_levels' => ['beginner', 'intermediate'],
            'age_groups' => ['children', 'teenagers'],
            'course_title' => 'Cours de guitare rock',
            'offers_trial' => true,
            'trial_price' => 15,
            'instrument_ids' => [$guitar->id, $drum->id],
            'style_ids' => [$rock->id, $metal->id],
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
        $responseData = $this->getResponseAsArray();

        $profile = $teacherProfileRepository->findOneBy(['user' => $user]);
        $this->assertNotNull($profile);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profile->id,
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
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
                ['instrument_id' => $drum->id, 'instrument_name' => 'Batteur', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [
                ['id' => $rock->id, 'name' => 'Rock', '@type' => 'TeacherProfileStyle'],
                ['id' => $metal->id, 'name' => 'Metal', '@type' => 'TeacherProfileStyle'],
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
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
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
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $guitarId = $guitar->id;

        $this->client->loginUser($user);
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

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            // Missing required fields: description, yearsOfExperience, instrumentIds
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=ad32d13f-c3d4-423b-909a-857b961eb720;2=ad32d13f-c3d4-423b-909a-857b961eb720',
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
                    'message' => 'Tu dois sélectionner au moins un instrument',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
            ],
            'title' => 'An error occurred',
            'detail' => 'description: La présentation est obligatoire
years_of_experience: Les années d\'expérience sont obligatoires
instrument_ids: Tu dois sélectionner au moins un instrument',
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=ad32d13f-c3d4-423b-909a-857b961eb720;2=ad32d13f-c3d4-423b-909a-857b961eb720',
            'description' => 'description: La présentation est obligatoire
years_of_experience: Les années d\'expérience sont obligatoires
instrument_ids: Tu dois sélectionner au moins un instrument',
        ]);
    }

    public function test_create_teacher_profile_validation_errors_invalid_years_of_experience(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => 80,
            'instrumentIds' => [$guitar->id],
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

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => -5,
            'instrumentIds' => [$guitar->id],
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

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitar->id],
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

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile', [
            'description' => 'Mon profil',
            'yearsOfExperience' => 5,
            'instrumentIds' => [$guitar->id],
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
        self::getContainer()->get(ServiceRepository::class);
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
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Ancien profil de professeur',
            'yearsOfExperience' => 3,
            'studentLevels' => [StudentLevel::BEGINNER->value],
            'ageGroups' => ['children'],
            'headline' => 'Cours de guitare',
            'offersTrial' => true,
            'trialPrice' => 10,
        ]);

        // Add initial instrument relation
        ServiceInstrumentFactory::new()->create([
            'service' => $teacherProfile,
            'instrument' => $guitar,
        ]);

        // Add initial style
        $teacherProfile->addStyle($rock);

        // Add initial location
        $initialLocation = new ServicePlace();
        $initialLocation->service = $teacherProfile;
        $initialLocation->type = ServicePlaceType::AtMyPlace;
        $initialLocation->address = '5 rue Initiale';
        $initialLocation->city = 'Lyon';
        $initialLocation->country = 'France';
        $teacherProfile->addPlace($initialLocation);

        // Add initial pricing
        $initialPricing = new ServiceOffer();
        $initialPricing->unit = OfferUnit::Lesson;
        $initialPricing->service = $teacherProfile;
        $initialPricing->quantity = SessionDuration::THIRTY_MINUTES->getMinutes();
        $initialPricing->price = 20;
        $teacherProfile->addOffer($initialPricing);

        // Add initial availability
        $initialAvailability = new ServiceAvailability();
        $initialAvailability->service = $teacherProfile;
        $initialAvailability->dayOfWeek = DayOfWeek::TUESDAY;
        $initialAvailability->startTime = new \DateTimeImmutable('10:00');
        $initialAvailability->endTime = new \DateTimeImmutable('12:00');
        $teacherProfile->addAvailability($initialAvailability);

        // Add initial package
        $initialPackage = new ServiceOffer();
        $initialPackage->unit = OfferUnit::Package;
        $initialPackage->service = $teacherProfile;
        $initialPackage->title = 'Pack initial';
        $initialPackage->description = 'Description initiale';
        $initialPackage->quantity = 3;
        $initialPackage->price = 50;
        $teacherProfile->addOffer($initialPackage);

        $entityManager->flush();

        $profileId = $teacherProfile->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => 'Nouveau profil mis à jour complètement',
            'years_of_experience' => 12,
            'student_levels' => ['intermediate', 'advanced'],
            'age_groups' => ['teenagers', 'adults', 'seniors'],
            'course_title' => 'Cours de batterie et piano jazz',
            'offers_trial' => false,
            'trial_price' => null,
            'instrument_ids' => [$drum->id, $piano->id],
            'style_ids' => [$metal->id, $jazz->id],
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
        $responseData = $this->getResponseAsArray();

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
                ['instrument_id' => $drum->id, 'instrument_name' => 'Batteur', '@type' => 'TeacherProfileInstrument'],
                ['instrument_id' => $piano->id, 'instrument_name' => 'Pianiste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [
                ['id' => $metal->id, 'name' => 'Metal', '@type' => 'TeacherProfileStyle'],
                ['id' => $jazz->id, 'name' => 'Jazz', '@type' => 'TeacherProfileStyle'],
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
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
        ]);
    }

    public function test_update_keeps_every_field_left_out(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [StudentLevel::BEGINNER->value],
            'ageGroups' => ['children'],
            'headline' => 'Guitare rock',
            'offersTrial' => true,
            'trialPrice' => 1000,
            'levelGoals' => ['beginner' => ['title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']]],
            'songs' => [['artist' => 'Radiohead', 'title' => 'Weird Fishes']],
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);
        $teacherProfile->addStyle($rock);

        $location = new ServicePlace();
        $location->type = ServicePlaceType::AtMyPlace;
        $location->address = '5 rue Initiale';
        $location->city = 'Lyon';
        $location->country = 'France';
        $location->latitude = '45.764';
        $location->longitude = '4.8357';
        $teacherProfile->addPlace($location);
        $pricing = new ServiceOffer();
        $pricing->unit = OfferUnit::Lesson;
        $pricing->quantity = SessionDuration::THIRTY_MINUTES->getMinutes();
        $pricing->price = 2000;
        $teacherProfile->addOffer($pricing);
        $availability = new ServiceAvailability();
        $availability->dayOfWeek = DayOfWeek::TUESDAY;
        $availability->startTime = new \DateTimeImmutable('10:00');
        $availability->endTime = new \DateTimeImmutable('12:00');
        $teacherProfile->addAvailability($availability);
        $package = new ServiceOffer();
        $package->unit = OfferUnit::Package;
        $package->title = 'Pack initial';
        $package->description = 'Trois cours';
        $package->quantity = 3;
        $package->price = 5000;
        $teacherProfile->addOffer($package);
        $socialLink = new ServiceLink();
        $socialLink->platform = SocialPlatform::YOUTUBE;
        $socialLink->url = 'https://www.youtube.com/@prof';
        $teacherProfile->addLink($socialLink);
        $entityManager->flush();

        // An app version that knows only some fields sends only those.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'years_of_experience' => 4,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 4,
            'student_levels' => ['beginner'],
            'age_groups' => ['children'],
            'course_title' => 'Guitare rock',
            'offers_trial' => true,
            'trial_price' => 1000,
            'locations' => [
                [
                    'id' => $location->id,
                    'type' => 'teacher_place',
                    'address' => '5 rue Initiale',
                    'city' => 'Lyon',
                    'country' => 'France',
                    'latitude' => '45.764',
                    'longitude' => '4.8357',
                    '@type' => 'TeacherProfileLocation',
                ],
            ],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [
                ['id' => $rock->id, 'name' => 'Rock', '@type' => 'TeacherProfileStyle'],
            ],
            'pricing' => [
                ['id' => $pricing->id, 'duration' => '30min', 'price' => 2000, '@type' => 'TeacherProfilePricing'],
            ],
            'availability' => [
                ['id' => $availability->id, 'day_of_week' => 'tuesday', 'start_time' => '10:00', 'end_time' => '12:00', '@type' => 'TeacherProfileAvailability'],
            ],
            'packages' => [
                ['id' => $package->id, 'title' => 'Pack initial', 'description' => 'Trois cours', 'sessions_count' => 3, 'price' => 5000, '@type' => 'TeacherProfilePackage'],
            ],
            'social_links' => [
                ['id' => $socialLink->id, 'platform' => 'youtube', 'url' => 'https://www.youtube.com/@prof', '@type' => 'TeacherProfileSocialLink'],
            ],
            'level_goals' => [
                ['level' => 'beginner', 'title' => 'Jouer ton premier morceau', 'points' => ['Les accords ouverts']],
            ],
            'songs' => [
                ['artist' => 'Radiohead', 'title' => 'Weird Fishes'],
            ],
        ]);
    }

    public function test_update_trial_switch_sent_without_a_price_clears_the_price(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'headline' => null,
            'offersTrial' => true,
            'trialPrice' => 1000,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);

        // A free trial: both clients send the switch with a null price.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'offers_trial' => true,
            'trial_price' => null,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => true,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
        ]);
    }

    public function test_update_trial_price_sent_alone_writes_the_price(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'headline' => null,
            'offersTrial' => true,
            'trialPrice' => 1000,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);

        // The switch left out keeps its stored value, the price sent is written.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', ['trial_price' => 1500], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => true,
            'trial_price' => 1500,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
        ]);
    }

    public function test_update_trial_switched_off_without_a_price_clears_the_price(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'headline' => null,
            'offersTrial' => true,
            'trialPrice' => 1000,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', ['offers_trial' => false], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
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
            'level_goals' => [],
            'songs' => [],
        ]);
        $this->assertNull(TeachingDetails::of($teacherProfile)->trialPrice);
    }

    public function test_update_empty_course_title_clears_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'headline' => 'Guitare rock',
            'offersTrial' => false,
            'trialPrice' => null,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);

        // What the web sends for a cleared title (#1327): an empty string, since null keeps it.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', ['course_title' => ''], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
            'age_groups' => [],
            'course_title' => '',
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
            'level_goals' => [],
            'songs' => [],
        ]);
        $this->assertSame('', $teacherProfile->headline);
    }

    public function test_update_keeps_one_price_per_lesson_length(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'offersTrial' => false,
            'trialPrice' => null,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);

        // A lesson length has one price (#1318): the first one sent wins.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'pricing' => [
                ['duration' => '30min', 'price' => 2500],
                ['duration' => '1h', 'price' => 4500],
                ['duration' => '30min', 'price' => 3000],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $guitar->id, 'instrument_name' => 'Guitariste', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [
                ['id' => $responseData['pricing'][0]['id'], 'duration' => '30min', 'price' => 2500, '@type' => 'TeacherProfilePricing'],
                ['id' => $responseData['pricing'][1]['id'], 'duration' => '1h', 'price' => 4500, '@type' => 'TeacherProfilePricing'],
            ],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
        ]);
    }

    public function test_update_refuses_a_cleared_description(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->teaching()->create(['user' => $user, 'description' => 'Cours de guitare']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'description' => '',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'description',
                    'message' => 'La présentation est obligatoire',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'description: La présentation est obligatoire',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'description: La présentation est obligatoire',
        ]);
    }

    public function test_update_replaces_the_social_links(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'headline' => null,
            'offersTrial' => false,
            'trialPrice' => null,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);
        $storedLink = new ServiceLink();
        $storedLink->platform = SocialPlatform::YOUTUBE;
        $storedLink->url = 'https://www.youtube.com/@ancien';
        $teacherProfile->addLink($storedLink);
        $entityManager->flush();

        // The same platform again: the stored row must go before the new one hits the unique key.
        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'social_links' => [
                ['platform' => 'youtube', 'url' => 'https://www.youtube.com/@nouveau'],
                ['platform' => 'instagram', 'url' => 'https://www.instagram.com/prof'],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
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
            'social_links' => [
                ['id' => $responseData['social_links'][0]['id'], 'platform' => 'youtube', 'url' => 'https://www.youtube.com/@nouveau', '@type' => 'TeacherProfileSocialLink'],
                ['id' => $responseData['social_links'][1]['id'], 'platform' => 'instagram', 'url' => 'https://www.instagram.com/prof', '@type' => 'TeacherProfileSocialLink'],
            ],
            'level_goals' => [],
            'songs' => [],
        ]);
    }

    public function test_update_skips_an_instrument_id_that_does_not_exist(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Cours de guitare',
            'yearsOfExperience' => 3,
            'studentLevels' => [],
            'ageGroups' => [],
            'headline' => null,
            'offersTrial' => false,
            'trialPrice' => null,
        ]);
        ServiceInstrumentFactory::new()->create(['service' => $teacherProfile, 'instrument' => $guitar]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/teacher-profile', [
            'instrument_ids' => [$drum->id, '019a8c2e-0000-7000-8000-000000000000'],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $teacherProfile->id,
            'description' => 'Cours de guitare',
            'years_of_experience' => 3,
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [
                ['instrument_id' => $drum->id, 'instrument_name' => 'Batteur', '@type' => 'TeacherProfileInstrument'],
            ],
            'styles' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
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
