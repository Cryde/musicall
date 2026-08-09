<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceCategoryGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_categories(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category1 = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Clips',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        $category2 = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Concerts',
            'position' => 1,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category1->id,
                    '@type' => 'FinanceCategory',
                    'id' => $category1->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Clips',
                    'parent_id' => null,
                    'position' => 0,
                    'has_children' => false,
                    'entry_count' => 0,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category2->id,
                    '@type' => 'FinanceCategory',
                    'id' => $category2->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Concerts',
                    'parent_id' => null,
                    'position' => 1,
                    'has_children' => false,
                    'entry_count' => 0,
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    /**
     * The count the delete confirmation names, so a wrong one tells a member their category is empty
     * on the dialog that decides whether they delete it. Counted per category rather than in total,
     * and only from entries filed directly under it.
     */
    public function test_get_categories_counts_the_entries_filed_under_each_one(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $withEntries = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        $empty = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Transport',
            'position' => 1,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        foreach ([FinanceEntryStatus::Paid, FinanceEntryStatus::Planned, FinanceEntryStatus::Committed] as $index => $status) {
            FinanceEntryFactory::new([
                'category' => $withEntries,
                'label' => 'Séance ' . $index,
                'type' => FinanceEntryType::Expense,
                'status' => $status,
                'scope' => FinanceEntryScope::Band,
                'amount' => 10000,
                'date' => new \DateTime('2026-03-0' . ($index + 1)),
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(
            ['Studio' => 3, 'Transport' => 0],
            array_combine(
                array_column($response['member'], 'name'),
                array_column($response['member'], 'entry_count'),
            ),
        );
        $this->assertSame((string) $empty->id, $response['member'][1]['id']);
    }

    public function test_get_categories_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_categories_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
