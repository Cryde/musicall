<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFileDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_by_uploader_soft_deletes(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var BandSpaceFileRepository $repo */
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->id);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->archiveDatetime);
    }

    public function test_delete_by_admin_soft_deletes(): void
    {
        $uploader = UserFactory::new()->asBaseUser()->create(['username' => 'uploader', 'email' => 'uploader@test.com']);
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'admin', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $uploader])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_by_random_member_returns_403(): void
    {
        $uploader = UserFactory::new()->asBaseUser()->create(['username' => 'uploader', 'email' => 'uploader@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $uploader])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul le créateur ou un administrateur peut supprimer ce fichier',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul le créateur ou un administrateur peut supprimer ce fichier',
        ]);
    }

    public function test_delete_already_archived_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Fichier introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Fichier introuvable',
        ]);
    }

    public function test_delete_task_attached_file_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => \Ramsey\Uuid\Uuid::uuid4(),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce fichier est attaché à une tâche. Détachez-le d'abord depuis la ressource concernée.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce fichier est attaché à une tâche. Détachez-le d'abord depuis la ressource concernée.",
        ]);

        /** @var BandSpaceFileRepository $repo */
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->id);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);
    }

    public function test_delete_finance_attached_file_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => \Ramsey\Uuid\Uuid::uuid4(),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce fichier est attaché à une entrée financière. Détachez-le d'abord depuis la ressource concernée.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce fichier est attaché à une entrée financière. Détachez-le d'abord depuis la ressource concernée.",
        ]);
    }
}
