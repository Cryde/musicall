<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskCommentFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\Message\MessageAttachmentFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Date;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Ma tâche',
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $task)],
            'search' => $this->buildTaskSearchShape($bandSpace),
        ]);
    }

    public function test_get_tasks_filter_by_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $todoTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?status=todo',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $todoTask)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?status=todo',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_tasks_filter_by_priority(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'priority' => TaskPriority::Normal])->create();
        $urgent = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'priority' => TaskPriority::Urgent])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?priority=urgent',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $urgent)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?priority=urgent',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_tasks_filter_by_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $taskInCategory = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => $category])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => null])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?category_id=' . $category->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $taskInCategory)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?category_id=' . $category->id,
                '@type' => 'PartialCollectionView',
            ],
        ]);
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->buildTaskShape($bandSpace, $user, $taskWithComments, ['comment_count' => 2]),
                $this->buildTaskShape($bandSpace, $user, $taskWithoutComments),
            ],
            'search' => $this->buildTaskSearchShape($bandSpace),
        ]);
    }

    public function test_get_tasks_includes_file_count_excluding_archived(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $taskWithFiles = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'With files',
            'position' => 0,
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();
        $taskWithoutFiles = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'No files',
            'position' => 1,
            'creationDatetime' => new \DateTime('2026-01-02 10:00:00'),
        ])->create();

        $activeFile1 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $activeFile2 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $archivedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();

        $taskUuid = Uuid::fromString($taskWithFiles->id);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $activeFile1,
            'sourceType' => 'task',
            'sourceId' => $taskUuid,
            'attachedBy' => $user,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $activeFile2,
            'sourceType' => 'task',
            'sourceId' => $taskUuid,
            'attachedBy' => $user,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $archivedFile,
            'sourceType' => 'task',
            'sourceId' => $taskUuid,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->buildTaskShape($bandSpace, $user, $taskWithFiles, ['file_count' => 2]),
                $this->buildTaskShape($bandSpace, $user, $taskWithoutFiles),
            ],
            'search' => $this->buildTaskSearchShape($bandSpace),
        ]);
    }

    /**
     * The conversation a card came from, on the board (#979).
     *
     * Read for the whole board in one query, beside the comment and file counts it already batches:
     * a lookup per task is the thing that makes a board expensive, and the query count below is what
     * would notice one appearing.
     */
    public function test_get_tasks_names_the_conversation_each_task_came_from(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();

        $titles = ['Ramener le câble', 'Réserver la salle', 'Relancer le programmateur'];
        $tasks = [];
        foreach ($titles as $position => $title) {
            $tasks[] = TaskFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'title' => $title,
                'position' => $position,
                'creationDatetime' => new \DateTime(sprintf('2026-01-0%d 10:00:00', $position + 1)),
            ])->create();
        }

        $messages = [];
        foreach ([0, 1] as $index) {
            $messages[$index] = MessageFactory::new([
                'thread' => $channel,
                'author' => $user,
                'content' => 'faut penser à ' . $titles[$index],
                'creationDatetime' => new \DateTime(sprintf('2026-01-0%d 09:00:00', $index + 1)),
            ])->create();
            MessageAttachmentFactory::new([
                'message' => $messages[$index],
                'targetType' => BandSpaceSearchResultType::Task,
                'targetId' => (string) $tasks[$index]->id,
                'label' => $titles[$index],
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $this->buildTaskShape($bandSpace, $user, $tasks[0], ['linked_message_id' => (string) $messages[0]->id]),
                $this->buildTaskShape($bandSpace, $user, $tasks[1], ['linked_message_id' => (string) $messages[1]->id]),
                // Created on the board and never mentioned, so there is nothing to go back to.
                $this->buildTaskShape($bandSpace, $user, $tasks[2]),
            ],
            'search' => $this->buildTaskSearchShape($bandSpace),
        ]);

        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to count the queries.');
        // Measured at 9: what the board already costs, plus the one query that reads the links for
        // every task at once. The margin is for a change to the firewall, not for a lookup per task,
        // which would put a real board in the hundreds.
        $this->assertLessThanOrEqual(
            10,
            $profile->getCollector('db')->getQueryCount(),
            'The conversation behind a card must be read once for the whole board, never once per task',
        );
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?query=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $matching)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?query=mixage',
                '@type' => 'PartialCollectionView',
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?query=pavel',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $matching)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?query=pavel',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_tasks_search_is_case_insensitive(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $matching = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Highlight Clip',
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?query=HIGHLIGHT',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $matching)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?query=HIGHLIGHT',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_tasks_search_composes_with_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $todoMixage = TaskFactory::new([
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?query=mixage&status=todo',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $todoMixage)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?query=mixage&status=todo',
                '@type' => 'PartialCollectionView',
            ],
        ]);
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?overdue=1',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->buildTaskShape($bandSpace, $user, $overdueTodo)],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?overdue=1',
                '@type' => 'PartialCollectionView',
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
            'creationDatetime' => new \DateTime('2026-04-02 00:00:00'),
        ])->create();
        $inRangeEnd = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'dueDate' => new \DateTimeImmutable('2026-05-31 23:59:00'),
            'creationDatetime' => new \DateTime('2026-04-01 00:00:00'),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'dueDate' => new \DateTimeImmutable('2026-06-01 00:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?due_date_from=2026-05-01&due_date_to=2026-05-31',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->buildTaskShape($bandSpace, $user, $inRangeStart),
                $this->buildTaskShape($bandSpace, $user, $inRangeEnd),
            ],
            'search' => $this->buildTaskSearchShape($bandSpace),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks?due_date_from=2026-05-01&due_date_to=2026-05-31',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_an_unparseable_due_date_bound_names_the_parameter(): void
    {
        // It used to fall back to the whole range in silence: the provider's own parser returned null
        // on anything it could not read, so a typo in the filter looked like a filter that matched
        // everything. Assert\Date on the operation says which parameter was wrong instead.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks?due_date_from=not-a-date',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'due_date_from',
                    'message' => 'Cette valeur n\'est pas une date valide.',
                    'code' => Date::INVALID_FORMAT_ERROR,
                ],
            ],
            'detail' => 'due_date_from: Cette valeur n\'est pas une date valide.',
            'type' => '/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            'title' => 'An error occurred',
            'description' => 'due_date_from: Cette valeur n\'est pas une date valide.',
        ]);
    }

    public function test_get_tasks_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildTaskShape(object $bandSpace, object $user, object $task, array $overrides = []): array
    {
        return array_merge([
            '@type' => 'Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            'id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'due_date' => $task->dueDate?->format('Y-m-d'),
            'created_by_id' => (string) $user->id,
            'created_by_username' => $user->username,
            'category_id' => $task->category?->id,
            'category_name' => $task->category?->name,
            'assignees' => [],
            'archive_datetime' => $task->archiveDatetime?->format(\DateTimeInterface::ATOM),
            'completed_datetime' => $task->completedDatetime?->format(\DateTimeInterface::ATOM),
            'position' => $task->position,
            'creation_datetime' => \DateTimeImmutable::createFromInterface($task->creationDatetime)->format(\DateTimeInterface::ATOM),
            'update_datetime' => $task->updateDatetime?->format(\DateTimeInterface::ATOM),
            'comment_count' => 0,
            'file_count' => 0,
            'linked_message_id' => null,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTaskSearchShape(object $bandSpace): array
    {
        return [
            '@type' => 'IriTemplate',
            'template' => '/api/band_spaces/' . $bandSpace->id . '/tasks{?status,priority}',
            'variableRepresentation' => 'BasicRepresentation',
            'mapping' => [
                ['@type' => 'IriTemplateMapping', 'variable' => 'status', 'property' => 'status', 'required' => false],
                ['@type' => 'IriTemplateMapping', 'variable' => 'priority', 'property' => 'priority', 'required' => false],
            ],
        ];
    }
}
