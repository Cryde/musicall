<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Validator\BandSpace\RecurrenceEndDateValidator;
use App\Validator\BandSpace\RecurrenceNoOverlapValidator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class FinanceRecurrenceCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_recurrence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => 50000,
                'startDate' => '2024-01-01',
                'endDate' => '2024-06-30',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $entries = $entryRepository->findByBandSpace($bandSpace);
        $this->assertCount(6, $entries);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $recurrenceId = $responseData['id'];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrenceId,
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Studio',
            'label' => 'Loyer salle',
            'type' => 'expense',
            'amount' => 50000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => '2024-01-01T00:00:00+00:00',
            'end_date' => '2024-06-30T00:00:00+00:00',
            'is_active' => true,
            'entry_count' => 6,
            'creation_datetime' => $responseData['creation_datetime'],
            'update_datetime' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $recurrenceId);
        $this->assertCount(1, $activities);
        $this->assertSame('recurrence_created', $activities[0]->type);
        $this->assertSame(
            ['label' => 'Loyer salle', 'amount' => 50000, 'interval' => 'monthly', 'generated_entries' => 6],
            $activities[0]->payload,
        );
    }

    public function test_create_recurrence_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => 50000,
                'startDate' => '2024-01-01',
                'endDate' => '2024-06-30',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_recurrence_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner_user', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => 50000,
                'startDate' => '2024-01-01',
                'endDate' => '2024-06-30',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_recurrence_overlap(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => 'Loyer existant',
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2024-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Loyer doublon',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => 50000,
                'startDate' => '2024-06-01',
                'endDate' => '2025-06-30',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . RecurrenceNoOverlapValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'interval',
                    'message' => 'Une récurrence avec le même intervalle existe déjà sur cette période pour cette catégorie',
                    'code' => RecurrenceNoOverlapValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'interval: Une récurrence avec le même intervalle existe déjà sur cette période pour cette catégorie',
            'description' => 'interval: Une récurrence avec le même intervalle existe déjà sur cette période pour cette catégorie',
            'type' => '/validation_errors/' . RecurrenceNoOverlapValidator::ERROR_CODE,
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_recurrence_end_date_before_start(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => 50000,
                'startDate' => '2024-06-01',
                'endDate' => '2024-01-01',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_BEFORE_START,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'La date de fin doit être postérieure à la date de début',
                    'code' => RecurrenceEndDateValidator::ERROR_CODE_BEFORE_START,
                ],
            ],
            'detail' => 'end_date: La date de fin doit être postérieure à la date de début',
            'description' => 'end_date: La date de fin doit être postérieure à la date de début',
            'type' => '/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_BEFORE_START,
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_recurrence_max_duration_exceeded(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => 50000,
                'startDate' => '2024-01-01',
                'endDate' => '2028-01-01',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_MAX_DURATION,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'La durée maximale est de 3 ans',
                    'code' => RecurrenceEndDateValidator::ERROR_CODE_MAX_DURATION,
                ],
            ],
            'detail' => 'end_date: La durée maximale est de 3 ans',
            'description' => 'end_date: La durée maximale est de 3 ans',
            'type' => '/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_MAX_DURATION,
            'title' => 'An error occurred',
        ]);
    }
}
