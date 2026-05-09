<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceTaskFileDetachTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_detach_clears_attachment_keeps_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::fromString($task->_real()->id),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/files/' . $file->_real()->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->_real()->id);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->attachedSourceType);
        $this->assertNull($reloaded->attachedSourceId);
        $this->assertNull($reloaded->archiveDatetime);
    }

    public function test_detach_with_archive_query_archives_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::fromString($task->_real()->id),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/files/' . $file->_real()->id . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->_real()->id);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->attachedSourceType);
        $this->assertNull($reloaded->attachedSourceId);
        $this->assertNotNull($reloaded->archiveDatetime);
    }

    public function test_detach_file_not_attached_to_task_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $otherTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::fromString($otherTask->_real()->id),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/files/' . $file->_real()->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le fichier n'est pas attaché à cette tâche",
            'status' => 404,
            'type' => '/errors/404',
            'description' => "Le fichier n'est pas attaché à cette tâche",
        ]);
    }

    public function test_detach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $member,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::fromString($task->_real()->id),
        ])->create();

        $this->client->loginUser($other->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id . '/files/' . $file->_real()->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }
}
