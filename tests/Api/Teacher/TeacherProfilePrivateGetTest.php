<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Entity\Teacher\TeacherAvailability;
use App\Entity\Teacher\TeacherProfileLocation;
use App\Entity\Teacher\TeacherProfilePackage;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Enum\Teacher\StudentLevel;
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

class TeacherProfilePrivateGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_my_teacher_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'my_teacher',
            'email' => 'my_teacher@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $teacherProfile = TeacherProfileFactory::new()->create([
            'user' => $user,
            'description' => 'Mon profil de professeur complet',
            'yearsOfExperience' => 15,
            'studentLevels' => [StudentLevel::BEGINNER->value, StudentLevel::INTERMEDIATE->value, StudentLevel::ADVANCED->value],
            'ageGroups' => [AgeGroup::CHILDREN->value, AgeGroup::TEENAGERS->value, AgeGroup::ADULTS->value],
            'courseTitle' => 'Cours de guitare',
            'offersTrial' => true,
            'trialPrice' => 1500,
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);

        // Add instrument
        TeacherProfileInstrumentFactory::new()->create([
            'teacherProfile' => $teacherProfile,
            'instrument' => $guitar,
        ]);

        // Add style
        $teacherProfile->_real()->addStyle($rock->_real());

        // Add location
        $location = new TeacherProfileLocation();
        $location->setType(LocationType::TEACHER_PLACE);
        $location->setAddress('123 rue de la Musique');
        $location->setCity('Paris');
        $location->setCountry('France');
        $location->setLatitude('48.8566');
        $location->setLongitude('2.3522');
        $location->setRadius(10);
        $teacherProfile->_real()->addLocation($location);

        // Add pricing
        $pricing = new TeacherProfilePricing();
        $pricing->setDuration(SessionDuration::ONE_HOUR);
        $pricing->setPrice(4500);
        $teacherProfile->_real()->addPricing($pricing);

        // Add availability
        $availability = new TeacherAvailability();
        $availability->setDayOfWeek(DayOfWeek::MONDAY);
        $availability->setStartTime(new \DateTimeImmutable('09:00'));
        $availability->setEndTime(new \DateTimeImmutable('12:00'));
        $teacherProfile->_real()->addAvailability($availability);

        // Add package
        $package = new TeacherProfilePackage();
        $package->setTitle('Forfait 10 cours');
        $package->setDescription('Pack de 10 cours d\'une heure');
        $package->setSessionsCount(10);
        $package->setPrice(40000);
        $teacherProfile->_real()->addPackage($package);

        $teacherProfile->_save();

        $profileId = $teacherProfile->getId();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/teacher-profile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profileId,
            'description' => 'Mon profil de professeur complet',
            'years_of_experience' => 15,
            'student_levels' => ['beginner', 'intermediate', 'advanced'],
            'age_groups' => ['children', 'teenagers', 'adults'],
            'course_title' => 'Cours de guitare',
            'offers_trial' => true,
            'trial_price' => 1500,
            'locations' => [
                [
                    '@type' => 'TeacherProfileLocation',
                    'id' => $location->getId(),
                    'type' => 'teacher_place',
                    'address' => '123 rue de la Musique',
                    'city' => 'Paris',
                    'country' => 'France',
                    'latitude' => '48.8566',
                    'longitude' => '2.3522',
                    'radius' => 10,
                ],
            ],
            'instruments' => [
                [
                    '@type' => 'TeacherProfileInstrument',
                    'instrument_id' => $guitar->getId(),
                    'instrument_name' => 'Guitariste',
                ],
            ],
            'styles' => [
                [
                    '@type' => 'TeacherProfileStyle',
                    'id' => $rock->getId(),
                    'name' => 'Rock',
                ],
            ],
            'pricing' => [
                [
                    '@type' => 'TeacherProfilePricing',
                    'id' => $pricing->getId(),
                    'duration' => '1h',
                    'price' => 4500,
                ],
            ],
            'availability' => [
                [
                    '@type' => 'TeacherProfileAvailability',
                    'id' => $availability->getId(),
                    'day_of_week' => 'monday',
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                ],
            ],
            'packages' => [
                [
                    '@type' => 'TeacherProfilePackage',
                    'id' => $package->getId(),
                    'title' => 'Forfait 10 cours',
                    'description' => 'Pack de 10 cours d\'une heure',
                    'sessions_count' => 10,
                    'price' => 40000,
                ],
            ],
            'social_links' => [],
        ]);
    }

    public function test_get_my_teacher_profile_not_logged_in(): void
    {
        $this->client->request('GET', '/api/user/teacher-profile');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_my_teacher_profile_no_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/teacher-profile');
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
}
