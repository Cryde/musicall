<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Repository\BandSpace\TaskActivityRepository;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskCommentCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_comment(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/comments',
            ['content' => 'Super, on avance bien !'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $comments = $commentRepo->findByTask($task->_real());
        $this->assertCount(1, $comments);

        $comment = $comments[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/comments/' . $comment->id,
            '@type' => 'TaskComment',
            'id' => $comment->id,
            'band_space_id' => $bandSpace->_real()->id,
            'task_id' => $task->_real()->id,
            'author_id' => $user->_real()->id,
            'author_username' => $user->_real()->username,
            'content' => 'Super, on avance bien !',
            'creation_datetime' => $comment->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(TaskActivityRepository::class);
        $activities = $activityRepo->findByTask($task->_real());
        $this->assertCount(1, $activities);
        $this->assertSame('comment_added', $activities[0]->type);
    }

    public function test_create_comment_with_mentions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $mentioned = UserFactory::new()->create(['username' => 'mentioned_user', 'email' => 'mentioned@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $mentioned])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/comments',
            ['content' => 'Hey @[' . $mentioned->_real()->id . '] regarde ça'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $activityRepo = self::getContainer()->get(TaskActivityRepository::class);
        $activities = $activityRepo->findByTask($task->_real());
        $this->assertCount(2, $activities);

        $types = array_map(fn($a) => $a->type, $activities);
        $this->assertContains('comment_added', $types);
        $this->assertContains('mention', $types);
    }

    public function test_create_comment_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/comments',
            ['content' => 'Forbidden comment'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
