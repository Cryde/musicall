<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Entity\Service\ServiceAvailability;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\SessionDuration;
use App\Enum\Teacher\StudentLevel;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TeacherProfilePrivateGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    public function test_get_my_teacher_profile(): void
    {
        self::mockTime('2026-06-15T10:00:00+00:00');
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'my_teacher',
            'email' => 'my_teacher@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'description' => 'Mon profil de professeur complet',
            'experienceStartYear' => 2011,
            'studentLevels' => [StudentLevel::BEGINNER->value, StudentLevel::INTERMEDIATE->value, StudentLevel::ADVANCED->value],
            'ageGroups' => [AgeGroup::CHILDREN->value, AgeGroup::TEENAGERS->value, AgeGroup::ADULTS->value],
            'headline' => 'Cours de guitare',
            'offersTrial' => true,
            'trialPrice' => 1500,
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);

        // Add instrument
        ServiceInstrumentFactory::new()->create([
            'service' => $teacherProfile,
            'instrument' => $guitar,
        ]);

        $realProfile = $teacherProfile;

        // Add style
        $realProfile->addStyle($rock);

        // Add location
        $location = new ServicePlace();
        $location->type = ServicePlaceType::AtMyPlace;
        $location->address = '123 rue de la Musique';
        $location->city = 'Paris';
        $location->country = 'France';
        $location->latitude = '48.8566';
        $location->longitude = '2.3522';
        $location->radius = 10;
        $realProfile->addPlace($location);

        // Add pricing
        $pricing = new ServiceOffer();
        $pricing->unit = OfferUnit::Lesson;
        $pricing->quantity = SessionDuration::ONE_HOUR->getMinutes();
        $pricing->price = 4500;
        $realProfile->addOffer($pricing);

        // Add availability
        $availability = new ServiceAvailability();
        $availability->dayOfWeek = DayOfWeek::MONDAY;
        $availability->startTime = new \DateTimeImmutable('09:00');
        $availability->endTime = new \DateTimeImmutable('12:00');
        $realProfile->addAvailability($availability);

        // Add package
        $package = new ServiceOffer();
        $package->unit = OfferUnit::Package;
        $package->title = 'Forfait 10 cours';
        $package->description = 'Pack de 10 cours d\'une heure';
        $package->quantity = 10;
        $package->price = 40000;
        $realProfile->addOffer($package);

        \Zenstruck\Foundry\Persistence\save($teacherProfile);

        $profileId = $teacherProfile->id;

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/teacher-profile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileOutput',
            '@id' => '/api/user/teacher-profile',
            '@type' => 'TeacherProfileOutput',
            'id' => $profileId,
            'description' => 'Mon profil de professeur complet',
            'years_of_experience' => 15,
            'experience_start_year' => 2011,
            'student_levels' => ['beginner', 'intermediate', 'advanced'],
            'age_groups' => ['children', 'teenagers', 'adults'],
            'course_title' => 'Cours de guitare',
            'offers_trial' => true,
            'trial_price' => 1500,
            'locations' => [
                [
                    '@type' => 'TeacherProfileLocation',
                    'id' => $location->id,
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
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                ],
            ],
            'styles' => [
                [
                    '@type' => 'TeacherProfileStyle',
                    'id' => $rock->id,
                    'name' => 'Rock',
                ],
            ],
            'pricing' => [
                [
                    '@type' => 'TeacherProfilePricing',
                    'id' => $pricing->id,
                    'duration' => '1h',
                    'price' => 4500,
                ],
            ],
            'availability' => [
                [
                    '@type' => 'TeacherProfileAvailability',
                    'id' => $availability->id,
                    'day_of_week' => 'monday',
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                ],
            ],
            'packages' => [
                [
                    '@type' => 'TeacherProfilePackage',
                    'id' => $package->id,
                    'title' => 'Forfait 10 cours',
                    'description' => 'Pack de 10 cours d\'une heure',
                    'sessions_count' => 10,
                    'price' => 40000,
                ],
            ],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
        ]);
    }

    public function test_get_my_teacher_profile_not_logged_in(): void
    {
        $this->client->request('GET', '/api/user/teacher-profile');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_my_teacher_profile_no_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
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
