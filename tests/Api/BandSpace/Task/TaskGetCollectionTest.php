<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskCommentFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Ma tâche',
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Collection',
            'totalItems' => 1,
        ]);
    }

    public function test_get_tasks_filter_by_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?status=todo',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['totalItems' => 1]);
    }

    public function test_get_tasks_filter_by_priority(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'priority' => TaskPriority::Normal])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'priority' => TaskPriority::Urgent])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?priority=urgent',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['totalItems' => 1]);
    }

    public function test_get_tasks_filter_by_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => $category])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => null])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?category_id=' . $category->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['totalItems' => 1]);
    }

    public function test_get_tasks_includes_comment_count(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $taskWithComments = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'With comments',
            'position' => 0,
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();
        $taskWithoutComments = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'No comments',
            'position' => 1,
            'creationDatetime' => new \DateTime('2026-01-02 10:00:00'),
        ])->create();
        TaskCommentFactory::new(['task' => $taskWithComments, 'author' => $user])->create();
        TaskCommentFactory::new(['task' => $taskWithComments, 'author' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'member' => [
                ['id' => $taskWithComments->_real()->id, 'comment_count' => 2],
                ['id' => $taskWithoutComments->_real()->id, 'comment_count' => 0],
            ],
        ]);
    }

    public function test_get_tasks_search_matches_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $matching = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Mixage du single',
        ])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Mastering'])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?query=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                ['id' => $matching->_real()->id],
            ],
        ]);
    }

    public function test_get_tasks_search_matches_description(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $matching = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Tâche A',
            'description' => 'Discuter avec Pavel des négociations',
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Tâche B',
            'description' => 'Autre sujet',
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?query=pavel',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                ['id' => $matching->_real()->id],
            ],
        ]);
    }

    public function test_get_tasks_search_is_case_insensitive(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Highlight Clip',
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?query=HIGHLIGHT',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['totalItems' => 1]);
    }

    public function test_get_tasks_search_composes_with_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Mixage',
            'status' => TaskStatus::Todo,
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Mixage',
            'status' => TaskStatus::Done,
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?query=mixage&status=todo',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['totalItems' => 1]);
    }

    public function test_get_tasks_filter_overdue_excludes_done_and_future(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $overdueTodo = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
            'dueDate' => new \DateTimeImmutable('-3 days'),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'dueDate' => new \DateTimeImmutable('-3 days'),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
            'dueDate' => new \DateTimeImmutable('+3 days'),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
            'dueDate' => null,
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?overdue=1',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                ['id' => $overdueTodo->_real()->id],
            ],
        ]);
    }

    public function test_get_tasks_filter_due_date_range(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'dueDate' => new \DateTimeImmutable('2026-04-30 12:00:00'),
        ])->create();
        $inRangeStart = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'dueDate' => new \DateTimeImmutable('2026-05-01 00:00:00'),
        ])->create();
        $inRangeEnd = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'dueDate' => new \DateTimeImmutable('2026-05-31 23:59:00'),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'dueDate' => new \DateTimeImmutable('2026-06-01 00:00:00'),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks?due_date_from=2026-05-01&due_date_to=2026-05-31',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['totalItems' => 2]);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($body['member'], 'id');
        $this->assertContains($inRangeStart->_real()->id, $ids);
        $this->assertContains($inRangeEnd->_real()->id, $ids);
    }

    public function test_get_tasks_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
