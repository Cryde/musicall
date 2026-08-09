<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileDeleteTest extends ApiTestCase
{
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

    /**
     * The admin share list hides shares of archived files, so trashing a file used to take its link off
     * the only screen that can revoke it while the row stayed live underneath. A restore weeks later
     * silently reopened the URL until its original expiry.
     */
    public function test_delete_revokes_the_share_links_of_the_file(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin])->create();
        $other = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin])->create();

        $live = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $admin,
            'tokenHash' => hash('sha256', 'live-token'),
            'expiryDatetime' => new \DateTimeImmutable('+30 days'),
        ])->create();
        $alreadyRevoked = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $admin,
            'tokenHash' => hash('sha256', 'revoked-token'),
            'revocationDatetime' => new \DateTimeImmutable('2026-01-02T03:04:05+00:00'),
        ])->create();
        $untouched = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $other,
            'createdBy' => $admin,
            'tokenHash' => hash('sha256', 'other-file-token'),
        ])->create();

        $bandSpaceId = $bandSpace->id;
        $fileId = (string) $file->id;
        $liveId = $live->id;
        $alreadyRevokedId = $alreadyRevoked->id;
        $untouchedId = $untouched->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpaceId . '/files/' . $fileId, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);

        /** @var BandSpaceFileShareRepository $shareRepo */
        $shareRepo = self::getContainer()->get(BandSpaceFileShareRepository::class);
        $this->assertNotNull($shareRepo->find($liveId)?->revocationDatetime);
        // An already revoked link keeps the date it was revoked on, it is not re-stamped.
        $this->assertSame(
            '2026-01-02T03:04:05+00:00',
            $shareRepo->find($alreadyRevokedId)?->revocationDatetime?->format(\DATE_ATOM),
        );
        // A link on another file is none of this delete's business.
        $this->assertNull($shareRepo->find($untouchedId)?->revocationDatetime);

        /** @var BandSpaceActivityRepository $activityRepo */
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $types = array_map(
            static fn ($activity): string => $activity->type,
            $activityRepo->findForResource($bandSpace, BandSpaceModule::File, $fileId),
        );
        sort($types);
        $this->assertSame(
            [BandSpaceFileActivityType::Archived->value, BandSpaceFileActivityType::ShareRevoked->value],
            $types,
        );
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
