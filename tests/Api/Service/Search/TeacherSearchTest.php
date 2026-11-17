<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The teacher search (#1314): its filters, its orders, and who it never shows. */
#[ResetDatabase]
class TeacherSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array BRUSSELS = ['lat' => '50.8503', 'lng' => '4.3517'];
    // 2.2 km from Brussels.
    private const array IXELLES = ['city' => 'Ixelles', 'latitude' => '50.8333', 'longitude' => '4.3667'];
    // 5.7 km.
    private const array UCCLE = ['city' => 'Uccle', 'latitude' => '50.8000', 'longitude' => '4.3333'];
    // 24.7 km.
    private const array LEUVEN = ['city' => 'Leuven', 'latitude' => '50.8798', 'longitude' => '4.7005'];
    // 89.7 km.
    private const array LIEGE = ['city' => 'Liège', 'latitude' => '50.6326', 'longitude' => '5.5797'];

    public function test_a_published_teacher_is_found_with_what_their_card_shows(): void
    {
        $bass = InstrumentFactory::new()->asBass()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $claire = $this->teacher('claire', [
            'studentLevels' => ['advanced', 'beginner'],
            'ageGroups' => ['adults'],
            'offersTrial' => true,
            'trialPrice' => 0,
        ], instruments: [$guitar, $bass], styles: [$rock]);
        $claire->user->profile->displayName = 'Claire Dumont';
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $claire]);
        ServicePlaceFactory::new()->remote()->create(['service' => $claire]);
        ServiceOfferFactory::new()->lesson(60, 4000)->create(['service' => $claire]);
        ServiceOfferFactory::new()->lesson(30, 2500)->create(['service' => $claire]);
        // Cheaper, but for 10 lessons: never what « dès » shows.
        ServiceOfferFactory::new()->package(10, 2000)->create(['service' => $claire]);
        ServiceMediaFactory::new()->create(['service' => $claire, 'position' => 1, 'embedId' => 'second', 'title' => 'Second']);
        ServiceMediaFactory::new()->create(['service' => $claire, 'position' => 0, 'embedId' => 'first', 'title' => 'First']);
        $this->flush();

        $this->client->request('GET', '/api/services/teaching');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/teacher_search_results/claire',
                    '@type' => 'TeacherSearchResult',
                    'username' => 'claire',
                    'name' => 'Claire Dumont',
                    'instruments' => [
                        ['@type' => 'TeacherSearchInstrument', 'id' => $guitar->id, 'name' => 'Guitare', 'slug' => 'guitare'],
                        ['@type' => 'TeacherSearchInstrument', 'id' => $bass->id, 'name' => 'Basse', 'slug' => 'basse'],
                    ],
                    'online' => true,
                    'lesson_price' => 2500,
                    'lesson_minutes' => 30,
                    'free_trial' => true,
                    'student_levels' => ['beginner', 'advanced'],
                    'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
                    'media' => ['@type' => 'TeacherSearchMedia', 'platform' => 'youtube', 'embed_id' => 'first', 'title' => 'First'],
                ],
            ],
        ]);
    }

    public function test_drafts_private_suspended_and_closed_teachers_are_never_found(): void
    {
        $this->teacher('shown');
        $this->teacher('draft')->status = ServiceStatus::Draft;
        $this->teacher('private')->user->profile->isPublic = false;
        $this->teacher('suspended')->user->suspensionDatetime = new DateTimeImmutable('2026-01-01');
        $this->teacher('closed')->user->deletionDatetime = new DateTimeImmutable('2026-01-01');
        $this->flush();

        $this->client->request('GET', '/api/services/teaching');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('shown')],
        ]);
    }

    public function test_a_teacher_blocked_either_way_is_not_found(): void
    {
        $viewer = UserFactory::new()->create(['username' => 'viewer']);
        $this->teacher('shown');
        $blockedByViewer = $this->teacher('blocked_by_viewer');
        $blockingViewer = $this->teacher('blocking_viewer');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blockedByViewer->user]);
        UserBlockFactory::new()->create(['blocker' => $blockingViewer->user, 'blocked' => $viewer]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/services/teaching');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('shown')],
        ]);
    }

    public function test_an_instrument_keeps_its_teachers_and_comes_first_on_their_card(): void
    {
        $bass = InstrumentFactory::new()->asBass()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $this->teacher('guitar_and_bass', instruments: [$guitar, $bass]);
        $this->teacher('guitar_only', instruments: [$guitar]);

        $this->client->request('GET', '/api/services/teaching', ['instrument' => $bass->id]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('guitar_and_bass', ['instruments' => [
                ['@type' => 'TeacherSearchInstrument', 'id' => $bass->id, 'name' => 'Basse', 'slug' => 'basse'],
                ['@type' => 'TeacherSearchInstrument', 'id' => $guitar->id, 'name' => 'Guitare', 'slug' => 'guitare'],
            ]])],
            'view' => ['@id' => '/api/services/teaching?instrument=' . $bass->id, '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_radius_keeps_the_places_in_reach_nearest_first(): void
    {
        ServicePlaceFactory::new()->atMyPlace(self::UCCLE)->create(['service' => $this->teacher('uccle')]);
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $this->teacher('ixelles')]);
        ServicePlaceFactory::new()->atMyPlace(self::LEUVEN)->create(['service' => $this->teacher('leuven')]);
        // Comes to the student from Leuven, up to 30 km: Brussels is in reach.
        ServicePlaceFactory::new()->atClientPlace(self::LEUVEN, 30)->create(['service' => $this->teacher('travels_from_leuven')]);
        // Up to 20 km from Liège only.
        ServicePlaceFactory::new()->atClientPlace(self::LIEGE, 20)->create(['service' => $this->teacher('travels_from_liege')]);
        ServicePlaceFactory::new()->remote()->create(['service' => $this->teacher('online_only')]);

        $this->client->request('GET', '/api/services/teaching', ['radius' => '10'] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $this->card('ixelles', ['distance' => 2.2]),
                $this->card('uccle', ['distance' => 5.7]),
                $this->card('travels_from_leuven', ['distance' => 24.7]),
            ],
            'view' => ['@id' => '/api/services/teaching?lat=50.8503&lng=4.3517&radius=10', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_online_also_finds_the_teachers_who_teach_online_wherever_they_are(): void
    {
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $this->teacher('ixelles')]);
        $liegeOnline = $this->teacher('liege_and_online');
        ServicePlaceFactory::new()->atMyPlace(self::LIEGE)->create(['service' => $liegeOnline]);
        ServicePlaceFactory::new()->remote()->create(['service' => $liegeOnline]);
        ServicePlaceFactory::new()->atMyPlace(self::LIEGE)->create(['service' => $this->teacher('liege')]);

        $this->client->request('GET', '/api/services/teaching', ['radius' => '10', 'online' => '1'] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->card('ixelles', ['distance' => 2.2]),
                // Found online, so no distance: « En ligne ».
                $this->card('liege_and_online', ['online' => true]),
            ],
            'view' => ['@id' => '/api/services/teaching?lat=50.8503&lng=4.3517&online=1&radius=10', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_point_without_a_radius_sorts_every_teacher_by_distance(): void
    {
        ServicePlaceFactory::new()->remote()->create(['service' => $this->teacher('online_only')]);
        ServicePlaceFactory::new()->atMyPlace(self::LIEGE)->create(['service' => $this->teacher('liege')]);
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $this->teacher('ixelles')]);

        $this->client->request('GET', '/api/services/teaching', self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $this->card('ixelles', ['distance' => 2.2]),
                $this->card('liege', ['distance' => 89.7]),
                $this->card('online_only', ['online' => true]),
            ],
            'view' => ['@id' => '/api/services/teaching?lat=50.8503&lng=4.3517', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_level_keeps_the_teachers_who_accept_it(): void
    {
        $this->teacher('beginners', ['studentLevels' => ['beginner', 'intermediate']]);
        $this->teacher('advanced_only', ['studentLevels' => ['advanced']]);

        $this->client->request('GET', '/api/services/teaching', ['level' => 'beginner']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('beginners', ['student_levels' => ['beginner', 'intermediate']])],
            'view' => ['@id' => '/api/services/teaching?level=beginner', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_an_age_group_keeps_the_teachers_who_accept_it(): void
    {
        $this->teacher('children', ['ageGroups' => ['children', 'teenagers']]);
        $this->teacher('adults_only', ['ageGroups' => ['adults']]);

        $this->client->request('GET', '/api/services/teaching', ['age_group' => 'teenagers']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('children')],
            'view' => ['@id' => '/api/services/teaching?age_group=teenagers', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_free_trial_keeps_the_teachers_whose_trial_lesson_is_free(): void
    {
        $this->teacher('free_trial', ['offersTrial' => true, 'trialPrice' => 0], created: '2024-01-03');
        // A trial with no price said is free too.
        $this->teacher('unpriced_trial', ['offersTrial' => true, 'trialPrice' => null], created: '2024-01-02');
        $this->teacher('paid_trial', ['offersTrial' => true, 'trialPrice' => 1500]);
        $this->teacher('no_trial');

        $this->client->request('GET', '/api/services/teaching', ['free_trial' => '1']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->card('free_trial', ['free_trial' => true]),
                $this->card('unpriced_trial', ['free_trial' => true]),
            ],
            'view' => ['@id' => '/api/services/teaching?free_trial=1', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_the_price_order_compares_lessons_per_30_minutes_and_puts_unpriced_teachers_last(): void
    {
        // 40 € the hour: 20 € per 30 minutes.
        ServiceOfferFactory::new()->lesson(60, 4000)->create(['service' => $this->teacher('hour_40')]);
        ServiceOfferFactory::new()->lesson(30, 2500)->create(['service' => $this->teacher('half_hour_25')]);
        // 27 € for 45 minutes: 18 € per 30 minutes.
        ServiceOfferFactory::new()->lesson(45, 2700)->create(['service' => $this->teacher('45_minutes_27')]);
        ServiceOfferFactory::new()->package(10, 15000)->create(['service' => $this->teacher('package_only', created: '2024-02-01')]);

        $this->client->request('GET', '/api/services/teaching', ['order' => 'price']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [
                $this->card('45_minutes_27', ['lesson_price' => 2700, 'lesson_minutes' => 45]),
                $this->card('hour_40', ['lesson_price' => 4000, 'lesson_minutes' => 60]),
                $this->card('half_hour_25', ['lesson_price' => 2500, 'lesson_minutes' => 30]),
                $this->card('package_only'),
            ],
            'view' => ['@id' => '/api/services/teaching?order=price', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_the_free_trial_order_puts_free_trials_first_then_the_nearest(): void
    {
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $this->teacher('near_no_trial')]);
        ServicePlaceFactory::new()->atMyPlace(self::LEUVEN)->create(['service' => $this->teacher('far_free', ['offersTrial' => true, 'trialPrice' => 0])]);
        ServicePlaceFactory::new()->atMyPlace(self::UCCLE)->create(['service' => $this->teacher('mid_free', ['offersTrial' => true, 'trialPrice' => 0])]);

        $this->client->request('GET', '/api/services/teaching', ['order' => 'free_trial', 'radius' => '50'] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $this->card('mid_free', ['distance' => 5.7, 'free_trial' => true]),
                $this->card('far_free', ['distance' => 24.7, 'free_trial' => true]),
                $this->card('near_no_trial', ['distance' => 2.2]),
            ],
            'view' => ['@id' => '/api/services/teaching?lat=50.8503&lng=4.3517&order=free_trial&radius=50', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_the_second_page_holds_the_teachers_after_the_first_twelve(): void
    {
        for ($day = 1; $day <= 13; ++$day) {
            $this->teacher(sprintf('teacher_%02d', $day), created: sprintf('2024-01-%02d', $day));
        }

        $this->client->request('GET', '/api/services/teaching', ['page' => '2']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherSearchResult',
            '@id' => '/api/services/teaching',
            '@type' => 'Collection',
            'totalItems' => 13,
            // The newest first: the oldest is the only one left for page 2.
            'member' => [$this->card('teacher_01')],
            'view' => [
                '@id' => '/api/services/teaching?page=2',
                '@type' => 'PartialCollectionView',
                'first' => '/api/services/teaching?page=1',
                'last' => '/api/services/teaching?page=2',
                'previous' => '/api/services/teaching?page=1',
            ],
        ]);
    }

    public function test_an_unknown_level_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['level' => 'expert']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'level', 'message' => "Ce niveau n'existe pas", 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "level: Ce niveau n'existe pas",
            'description' => "level: Ce niveau n'existe pas",
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_unknown_age_group_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['age_group' => 'toddlers']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'age_group', 'message' => "Ce public n'existe pas", 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "age_group: Ce public n'existe pas",
            'description' => "age_group: Ce public n'existe pas",
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_unknown_order_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['order' => 'cheapest']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'order', 'message' => "Ce tri n'existe pas", 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "order: Ce tri n'existe pas",
            'description' => "order: Ce tri n'existe pas",
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_flag_other_than_0_or_1_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['free_trial' => 'yes']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'free_trial', 'message' => 'La valeur doit être 0 ou 1', 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => 'free_trial: La valeur doit être 0 ou 1',
            'description' => 'free_trial: La valeur doit être 0 ou 1',
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_latitude_that_is_not_a_number_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['lat' => "50.85\0", 'lng' => '4.35']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'lat', 'message' => 'Les coordonnées du lieu ne sont pas valides', 'code' => Regex::REGEX_FAILED_ERROR],
            ],
            'detail' => 'lat: Les coordonnées du lieu ne sont pas valides',
            'description' => 'lat: Les coordonnées du lieu ne sont pas valides',
            'type' => '/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_page_that_is_not_a_whole_number_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['page' => '0']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'page', 'message' => "Le numéro de page n'est pas valide", 'code' => Range::NOT_IN_RANGE_ERROR],
            ],
            'detail' => "page: Le numéro de page n'est pas valide",
            'description' => "page: Le numéro de page n'est pas valide",
            'type' => '/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_longitude_out_of_range_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['lat' => '50.85', 'lng' => '181']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'lng', 'message' => 'Les coordonnées du lieu ne sont pas valides', 'code' => Range::NOT_IN_RANGE_ERROR],
            ],
            'detail' => 'lng: Les coordonnées du lieu ne sont pas valides',
            'description' => 'lng: Les coordonnées du lieu ne sont pas valides',
            'type' => '/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_radius_out_of_range_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['radius' => '501'] + self::BRUSSELS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'radius', 'message' => 'La distance doit être comprise entre 1 et 500 km', 'code' => Range::NOT_IN_RANGE_ERROR],
            ],
            'detail' => 'radius: La distance doit être comprise entre 1 et 500 km',
            'description' => 'radius: La distance doit être comprise entre 1 et 500 km',
            'type' => '/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_unknown_instrument_is_not_found(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['instrument' => '01a1e874-d06d-70df-8c07-b94cba3921d4']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Instrument introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Instrument introuvable',
        ]);
    }

    public function test_an_instrument_that_is_not_an_id_is_refused(): void
    {
        $this->client->request('GET', '/api/services/teaching', ['instrument' => 'basse']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'instrument', 'message' => "L'instrument n'est pas valide", 'code' => Uuid::INVALID_CHARACTERS_ERROR],
            ],
            'detail' => "instrument: L'instrument n'est pas valide",
            'description' => "instrument: L'instrument n'est pas valide",
            'type' => '/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /**
     * A published teaching service, its details given as TeachingDetails' own fields.
     *
     * @param array<string, mixed> $details
     * @param list<Instrument>     $instruments
     * @param list<Style>          $styles
     */
    private function teacher(string $username, array $details = [], array $instruments = [], array $styles = [], string $created = '2024-01-01'): Service
    {
        $user = UserFactory::new()->create(['username' => $username]);
        $service = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'creationDatetime' => new DateTimeImmutable($created . 'T10:00:00+00:00'),
            ...$details,
        ]);
        foreach ($instruments as $instrument) {
            ServiceInstrumentFactory::new()->create(['service' => $service, 'instrument' => $instrument]);
        }
        foreach ($styles as $style) {
            $service->addStyle($style);
        }
        $this->flush();

        return $service;
    }

    private function flush(): void
    {
        self::getContainer()->get('doctrine')->getManager()->flush();
    }

    /**
     * One result as the search answers it: a teacher with nothing filled in, but what is given.
     *
     * @param array<string, mixed> $filled
     *
     * @return array<string, mixed>
     */
    private function card(string $username, array $filled = []): array
    {
        return [
            '@id' => '/api/teacher_search_results/' . $username,
            '@type' => 'TeacherSearchResult',
            'username' => $username,
            'name' => $username,
            'instruments' => [],
            'online' => false,
            'free_trial' => false,
            'student_levels' => [],
            'styles' => [],
            ...$filled,
        ];
    }
}
