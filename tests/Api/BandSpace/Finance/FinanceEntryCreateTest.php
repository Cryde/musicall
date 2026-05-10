<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Validator\BandSpace\FinanceAmountRangeValidator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class FinanceEntryCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_expense_entry(): void
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
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Mixage',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 50000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $entries = $entryRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Studio',
            'label' => 'Mixage',
            'type' => 'expense',
            'status' => 'planned',
            'amount' => 50000,
            'amount_min' => null,
            'amount_max' => null,
            'date' => '2024-01-15',
            'scope' => 'band',
            'member_id' => null,
            'member_name' => null,
            'recurrence_id' => null,
            'is_former_member' => false,
            'split_warning' => false,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_created', $activities[0]->type);
        $this->assertSame(
            ['label' => 'Mixage', 'amount' => 50000, 'type' => 'expense', 'status' => 'planned'],
            $activities[0]->payload,
        );
    }

    public function test_create_income_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Concerts',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Concert du 15 mars',
                'type' => 'income',
                'status' => 'paid',
                'scope' => 'band',
                'amount' => 15000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $entries = $entryRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Concerts',
            'label' => 'Concert du 15 mars',
            'type' => 'income',
            'status' => 'paid',
            'amount' => 15000,
            'amount_min' => null,
            'amount_max' => null,
            'date' => '2024-01-15',
            'scope' => 'band',
            'member_id' => null,
            'member_name' => null,
            'recurrence_id' => null,
            'is_former_member' => false,
            'split_warning' => false,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_entry_with_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Perso',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Cordes guitare',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'personal',
                'amount' => 3000,
                'memberId' => (string) $membership->id,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $entries = $entryRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Perso',
            'label' => 'Cordes guitare',
            'type' => 'expense',
            'status' => 'planned',
            'amount' => 3000,
            'amount_min' => null,
            'amount_max' => null,
            'date' => '2024-01-15',
            'scope' => 'personal',
            'member_id' => $membership->id,
            'member_name' => $user->username,
            'recurrence_id' => null,
            'is_former_member' => false,
            'split_warning' => false,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_entry_invalid_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                'label' => 'Test',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 1000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_create_entry_not_member(): void
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
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Forbidden',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 1000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_entry_inactive_member(): void
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
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Forbidden',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 1000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_entry_validation_empty_label(): void
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
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => '',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 1000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'label',
                    'message' => 'Veuillez spécifier un libellé',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'label: Veuillez spécifier un libellé',
            'description' => 'label: Veuillez spécifier un libellé',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_entry_validation_invalid_date(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Test',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 1000,
                'date' => 'not-a-date',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/69819696-02ac-4a99-9ff0-14e127c4d1bc',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'date',
                    'message' => 'Le format de la date est invalide (attendu : AAAA-MM-JJ)',
                    'code' => '69819696-02ac-4a99-9ff0-14e127c4d1bc',
                ],
            ],
            'detail' => 'date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'description' => 'date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'type' => '/validation_errors/69819696-02ac-4a99-9ff0-14e127c4d1bc',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_entry_validation_missing_date(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Test',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 1000,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'date',
                    'message' => 'Veuillez spécifier une date',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'date: Veuillez spécifier une date',
            'description' => 'date: Veuillez spécifier une date',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_entry_with_amount_range(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Concerts',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Sono (devis en cours)',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amountMin' => 80000,
                'amountMax' => 120000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $entries = $entryRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Concerts',
            'label' => 'Sono (devis en cours)',
            'type' => 'expense',
            'status' => 'planned',
            'amount' => null,
            'amount_min' => 80000,
            'amount_max' => 120000,
            'date' => '2024-01-15',
            'scope' => 'band',
            'member_id' => null,
            'member_name' => null,
            'recurrence_id' => null,
            'is_former_member' => false,
            'split_warning' => false,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_entry_validation_amount_and_range_exclusive(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => 'Test',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'amount' => 50000,
                'amountMin' => 40000,
                'amountMax' => 60000,
                'date' => '2024-01-15',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_EXCLUSIVE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => 'Vous ne pouvez pas définir un montant exact et une fourchette en même temps',
                    'code' => FinanceAmountRangeValidator::ERROR_CODE_EXCLUSIVE,
                ],
            ],
            'detail' => 'amount: Vous ne pouvez pas définir un montant exact et une fourchette en même temps',
            'description' => 'amount: Vous ne pouvez pas définir un montant exact et une fourchette en même temps',
            'type' => '/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_EXCLUSIVE,
            'title' => 'An error occurred',
        ]);
    }
}
