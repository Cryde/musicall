<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Enum\BandSpace\TaskStatus;
use App\Mercure\MercureTopic;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The `band_space_changed` signal (#1102): who hears it and what it says. Each endpoint's own test
 * pins its answer, so only the status is checked here.
 */
#[ResetDatabase]
class BandSpaceChangeSignalTest extends ApiTestCase
{
    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const array MERGE_PATCH = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /**
     * One write per module: a create recorded in the feed for most, and for agenda, task and file a
     * write that never reaches the feed, so the signal cannot only be riding on the recorder.
     *
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    public static function moduleWriteProvider(): iterable
    {
        yield 'agenda: an absence' => ['POST', '/absences', ['startDate' => '2026-08-10', 'endDate' => '2026-08-12'], 'agenda'];
        yield 'task: a category' => ['POST', '/task-categories', ['name' => 'Répétitions'], 'task'];
        yield 'file: a tag' => ['POST', '/tags', ['name' => 'Riders'], 'file'];
        yield 'notes' => ['POST', '/notes', ['title' => 'Idées pour le prochain album'], 'notes'];
        yield 'setlist' => ['POST', '/setlists', ['name' => 'Summer tour 2026'], 'setlist'];
        yield 'rider' => ['POST', '/tech_riders', ['name' => 'Technical rider 2026'], 'rider'];
        yield 'finance' => ['POST', '/finance/categories', ['name' => 'Clips'], 'finance'];
        yield 'settings' => ['PATCH', '', ['name' => 'Les Nouveaux Rockeurs'], 'settings'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('moduleWriteProvider')]
    public function test_a_write_signals_every_active_member(string $method, string $path, array $body, string $module): void
    {
        [$space, $admin, $member, $left, $kicked, $elsewhere] = $this->band();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($admin);
        $this->client->jsonRequest($method, '/api/band_spaces/' . $space->id . $path, $body, $method === 'PATCH' ? self::MERGE_PATCH : self::JSON);

        $this->assertResponseIsSuccessful();
        $signals = $this->changeSignals($hub);
        $this->assertCount(1, $signals);
        // The author too, so their other devices follow.
        $this->assertEqualsCanonicalizing(
            [MercureTopic::userNotifications((string) $admin->id), MercureTopic::userNotifications((string) $member->id)],
            $signals[0]->getTopics(),
        );
        foreach ([$left, $kicked, $elsewhere] as $excluded) {
            $this->assertNotContains(MercureTopic::userNotifications((string) $excluded->id), $hub->publishedTopics());
        }
        $this->assertTrue($signals[0]->isPrivate());
        $this->assertSame($this->tag($space, $module), $signals[0]->getData());
    }

    /**
     * A status and a due date are two feed entries, and still one signal.
     */
    public function test_a_request_recording_several_activities_signals_once(): void
    {
        [$space, $admin] = $this->band();
        $task = TaskFactory::new(['bandSpace' => $space, 'createdBy' => $admin, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $space->id . '/tasks/' . $task->id,
            ['status' => 'in_progress', 'due_date' => '2026-11-02'],
            self::MERGE_PATCH,
        );

        $this->assertResponseIsSuccessful();
        $signals = $this->changeSignals($hub);
        $this->assertCount(1, $signals);
        $this->assertSame($this->tag($space, 'task'), $signals[0]->getData());
    }

    public function test_a_reorder_signals_the_task_module(): void
    {
        [$space, $admin] = $this->band();
        $first = TaskFactory::new(['bandSpace' => $space, 'createdBy' => $admin, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $second = TaskFactory::new(['bandSpace' => $space, 'createdBy' => $admin, 'status' => TaskStatus::Todo, 'position' => 1])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/tasks/reorder', [
            'positions' => [['id' => (string) $second->id, 'position' => 0], ['id' => (string) $first->id, 'position' => 1]],
        ], self::JSON);

        $this->assertResponseIsSuccessful();
        $signals = $this->changeSignals($hub);
        $this->assertCount(1, $signals);
        $this->assertSame($this->tag($space, 'task'), $signals[0]->getData());
    }

    /**
     * What a member keeps to themselves stays so: the band does not even learn that it moved.
     */
    public function test_a_personal_finance_entry_signals_nothing(): void
    {
        [$space, $admin] = $this->band();
        $membership = $this->membershipOf($space, $admin);
        $category = FinanceCategoryFactory::new(['bandSpace' => $space, 'name' => 'Perso', 'position' => 0])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/finance/entries', [
            'categoryId' => (string) $category->id,
            'label' => 'Cordes guitare',
            'type' => 'expense',
            'status' => 'planned',
            'scope' => 'personal',
            'amount' => 3000,
            'memberId' => (string) $membership->id,
            'date' => '2026-10-15',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame([], $this->changeSignals($hub));
    }

    public function test_a_refused_write_signals_nothing(): void
    {
        [$space, $admin] = $this->band();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/task-categories', ['name' => ''], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame([], $this->changeSignals($hub));
    }

    public function test_an_unreachable_hub_does_not_fail_the_write(): void
    {
        [$space, $admin] = $this->band();
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/task-categories', ['name' => 'Répétitions'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertCount(1, self::getContainer()->get(TaskCategoryRepository::class)->findBy(['bandSpace' => $space->id]));
    }

    /**
     * The admin, a member, one who left, one kicked, and somebody active in another space.
     *
     * @return array{0: BandSpace, 1: User, 2: User, 3: User, 4: User, 5: User}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Rockeurs']);
        $admin = UserFactory::new()->create(['username' => 'admin', 'email' => 'admin@example.com']);
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $left = UserFactory::new()->create(['username' => 'left', 'email' => 'left@example.com']);
        $kicked = UserFactory::new()->create(['username' => 'kicked', 'email' => 'kicked@example.com']);
        $elsewhere = UserFactory::new()->create(['username' => 'elsewhere', 'email' => 'elsewhere@example.com']);

        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $left, 'status' => MembershipStatus::Left])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $kicked, 'status' => MembershipStatus::Kicked])->create();
        BandSpaceMembershipFactory::new(['user' => $elsewhere])->create();

        return [$space, $admin, $member, $left, $kicked, $elsewhere];
    }

    private function membershipOf(BandSpace $space, User $user): BandSpaceMembership
    {
        return BandSpaceMembershipFactory::repository()->findOneBy(['bandSpace' => $space, 'user' => $user]);
    }

    /**
     * Only this signal: some writes also notify somebody, which publishes a signal of its own.
     *
     * @return list<Update>
     */
    private function changeSignals(RecordingHub $hub): array
    {
        return array_values(array_filter(
            $hub->updates,
            static fn (Update $update): bool => str_contains($update->getData(), '"band_space_changed"'),
        ));
    }

    private function tag(BandSpace $space, string $module): string
    {
        return json_encode(['type' => 'band_space_changed', 'band_space_id' => (string) $space->id, 'module' => $module], JSON_THROW_ON_ERROR);
    }
}
