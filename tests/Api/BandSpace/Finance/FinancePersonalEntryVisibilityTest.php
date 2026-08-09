<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceCategory;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A personal entry is readable by the member it belongs to and by nobody else.
 *
 * Only writing was ever gated, so every read path handed a member somebody else's private spending:
 * the entry list and a direct GET showed the label, the date and the amount, and the aggregates
 * carried the amounts in another shape. There is one test per read path rather than one for the
 * module, because each is a separate query and closing four of five is not closing anything.
 *
 * Each test spends its single authenticated request on the read under test: the api firewall is
 * stateless, so a second one comes back 401.
 */
#[ResetDatabase]
class FinancePersonalEntryVisibilityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const OTHERS_SECRET = 'Remboursement dette perso';

    public function test_the_entry_list_hides_a_personal_entry_of_another_member(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBandWithBothKindsOfEntry();

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(
            ['Local de répétition', 'Mes cordes'],
            array_column($response['member'], 'label'),
            'the band entry and the viewer\'s own personal one, and nothing else'
        );
        $this->assertSame(2, $response['totalItems']);
    }

    public function test_reading_a_personal_entry_of_another_member_answers_like_an_unknown_id(): void
    {
        [$bandSpace, $viewer, , $othersEntryId] = $this->createBandWithBothKindsOfEntry();

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $othersEntryId,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        // 404 rather than 403: a 403 would confirm the entry exists, which is part of what is private.
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Entrée introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Entrée introuvable',
        ]);
    }

    public function test_the_summary_leaves_a_personal_entry_of_another_member_out_of_every_total(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBandWithBothKindsOfEntry();

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();

        // 40000 is the band entry, 5000 the viewer's own. The other member's 90000 appears nowhere,
        // neither in total_personal nor summed into the pole, where it would have been an amount
        // wearing a different hat.
        $this->assertSame(40000, $response['total_expense']);
        $this->assertSame(5000, $response['total_personal']);
        $this->assertSame(
            [['id' => (string) $category->id, 'name' => 'Studio', 'paid' => 45000, 'committed' => 0, 'planned' => 0]],
            $response['by_category']
        );
    }

    public function test_the_upcoming_list_hides_a_personal_entry_of_another_member(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBand();
        $others = $this->addMember($bandSpace, 'other_member', 'other@test.com');

        $this->createEntry($category, 'Avance studio', 40000, FinanceEntryStatus::Planned, date: '+10 days');
        $this->createEntry($category, self::OTHERS_SECRET, 90000, FinanceEntryStatus::Planned, member: $others, date: '+11 days');

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(
            ['Avance studio'],
            array_column($response['upcoming_entries'], 'label'),
            'the other member\'s forecast is not an upcoming deadline of this band'
        );
    }

    /**
     * Finance entries are a source of the shared calendar, so a personal one was printed on it with
     * its label and its amount, for the whole band to read. The issue never mentioned this path.
     */
    public function test_the_agenda_hides_a_personal_entry_of_another_member(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBand();
        $others = $this->addMember($bandSpace, 'other_member', 'other@test.com');

        $this->createEntry($category, 'Facture studio', 40000, FinanceEntryStatus::Planned, date: '2026-09-10');
        $this->createEntry($category, self::OTHERS_SECRET, 90000, FinanceEntryStatus::Planned, member: $others, date: '2026-09-11');

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-09-01&to=2026-09-30',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(['Facture studio'], array_column($response['member'], 'title'));
    }

    /** The other half of the rule: the owner keeps seeing their own, or this would be a data loss. */
    public function test_the_owner_still_reads_their_own_personal_entry(): void
    {
        [$bandSpace, , $category] = $this->createBand();
        $owner = $this->addMember($bandSpace, 'owner_member', 'owner@test.com');
        $entry = $this->createEntry($category, 'Mes cordes', 5000, FinanceEntryStatus::Paid, member: $owner);

        $this->client->loginUser($owner->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('Mes cordes', $response['label']);
        $this->assertSame(5000, $response['amount']);
        $this->assertSame('personal', $response['scope']);
    }

    /**
     * Personal means personal, so an admin is not an exception. Deciding otherwise would make the
     * word mean "private unless somebody has a role", which is not what a member typing it expects.
     */
    public function test_an_admin_reads_no_more_than_anybody_else(): void
    {
        [$bandSpace, , $category] = $this->createBand();
        $admin = $this->addMember($bandSpace, 'admin_member', 'admin@test.com', Role::Admin);
        $others = $this->addMember($bandSpace, 'other_member', 'other@test.com');
        $entry = $this->createEntry($category, self::OTHERS_SECRET, 90000, FinanceEntryStatus::Paid, member: $others);

        $this->client->loginUser($admin->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * The band wide journal was the widest hole of all: it carries the label and the amount in its
     * payload, it fires on every ordinary create, edit and delete, and every member can read it. No
     * id to guess and no unusual action required, so closing the query paths alone would have left
     * the same numbers on the dashboard's recent activity widget.
     */
    public function test_creating_a_personal_entry_writes_nothing_to_the_band_journal(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBand();

        $this->client->loginUser($viewer->user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $category->id,
                'label' => self::OTHERS_SECRET,
                'type' => 'expense',
                'status' => 'paid',
                'scope' => 'personal',
                'amount' => 90000,
                'memberId' => (string) $viewer->id,
                'date' => '2026-03-01',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Finance, $this->getResponseAsArray()['id']);
        $this->assertSame([], $activities, 'nothing about a personal entry is written to a shared table');
    }

    /** A personal recurrence carries its own label and amount, so it was the same leak one level up. */
    public function test_the_recurrence_list_hides_a_personal_recurrence_of_another_member(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBand();
        $others = $this->addMember($bandSpace, 'other_member', 'other@test.com');

        $bandRecurrence = $this->createRecurrence($category, 'Loyer du local', FinanceEntryScope::Band);
        $othersRecurrence = $this->createRecurrence($category, 'Abonnement thérapie', FinanceEntryScope::Personal);
        // Ownership is read from the entries a recurrence planned, exactly as the write checks do.
        $this->createEntry($category, 'Abonnement thérapie', 8000, FinanceEntryStatus::Paid, member: $others, recurrence: $othersRecurrence);

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['Loyer du local'],
            array_column($this->getResponseAsArray()['member'], 'label')
        );
    }

    public function test_reading_a_personal_recurrence_of_another_member_answers_like_an_unknown_id(): void
    {
        [$bandSpace, $viewer, $category] = $this->createBand();
        $others = $this->addMember($bandSpace, 'other_member', 'other@test.com');
        $othersRecurrence = $this->createRecurrence($category, 'Abonnement thérapie', FinanceEntryScope::Personal);
        $this->createEntry($category, 'Abonnement thérapie', 8000, FinanceEntryStatus::Paid, member: $others, recurrence: $othersRecurrence);

        $this->client->loginUser($viewer->user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $othersRecurrence->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Récurrence introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Récurrence introuvable',
        ]);
    }

    /**
     * @return array{0: BandSpace, 1: BandSpaceMembership, 2: FinanceCategory}
     */
    private function createBand(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        return [$bandSpace, $membership, $category];
    }

    /**
     * A band entry, one personal entry owned by the viewer, and one owned by somebody else.
     *
     * @return array{0: BandSpace, 1: BandSpaceMembership, 2: FinanceCategory, 3: string}
     */
    private function createBandWithBothKindsOfEntry(): array
    {
        [$bandSpace, $viewer, $category] = $this->createBand();
        $others = $this->addMember($bandSpace, 'other_member', 'other@test.com');

        // Distinct dates: the list orders by date descending, and a tie leaves the order to the
        // database, which would make the assertion below flake rather than fail.
        $this->createEntry($category, 'Local de répétition', 40000, FinanceEntryStatus::Paid, date: '2026-03-03');
        $this->createEntry($category, 'Mes cordes', 5000, FinanceEntryStatus::Paid, member: $viewer, date: '2026-03-02');
        $othersEntry = $this->createEntry($category, self::OTHERS_SECRET, 90000, FinanceEntryStatus::Paid, member: $others, date: '2026-03-01');

        return [$bandSpace, $viewer, $category, (string) $othersEntry->id];
    }

    private function createRecurrence(FinanceCategory $category, string $label, FinanceEntryScope $scope): FinanceRecurrence
    {
        return FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => $label,
            'type' => FinanceEntryType::Expense,
            'scope' => $scope,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => 8000,
            'startDate' => new \DateTime('2026-01-01'),
            'endDate' => new \DateTime('2026-12-01'),
            'isActive' => true,
        ])->create();
    }

    private function addMember(BandSpace $bandSpace, string $username, string $email, Role $role = Role::User): BandSpaceMembership
    {
        $user = UserFactory::new()->create(['username' => $username, 'email' => $email]);

        return BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'role' => $role,
        ])->create();
    }

    private function createEntry(
        FinanceCategory $category,
        string $label,
        int $amount,
        FinanceEntryStatus $status,
        ?BandSpaceMembership $member = null,
        string $date = '2026-03-01',
        ?FinanceRecurrence $recurrence = null,
    ): \App\Entity\BandSpace\FinanceEntry {
        return FinanceEntryFactory::new([
            'category' => $category,
            'label' => $label,
            'type' => FinanceEntryType::Expense,
            'status' => $status,
            'scope' => $member instanceof BandSpaceMembership ? FinanceEntryScope::Personal : FinanceEntryScope::Band,
            'member' => $member,
            'amount' => $amount,
            'date' => new \DateTime($date),
            'recurrence' => $recurrence,
        ])->create();
    }
}
