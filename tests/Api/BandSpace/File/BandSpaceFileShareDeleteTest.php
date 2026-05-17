<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileShareDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_admin_revokes_share_records_activity(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin])->create();

        $share = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $admin,
            'tokenHash' => hash('sha256', 'token-revoke'),
            'expiryDatetime' => new \DateTimeImmutable('+1 day'),
        ])->create();

        $bandSpaceId = $bandSpace->id;
        $shareId = $share->id;
        $fileId = $file->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/shares/' . $shareId,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        /** @var BandSpaceFileShareRepository $repo */
        $repo = self::getContainer()->get(BandSpaceFileShareRepository::class);
        $reloaded = $repo->find($shareId);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->revocationDatetime);

        /** @var BandSpaceActivityRepository $activityRepo */
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $revoked = array_values(array_filter($activities, fn (\App\Entity\BandSpace\BandSpaceActivity $a): bool => $a->type === 'share_revoked'));
        $this->assertCount(1, $revoked);
        $this->assertSame($shareId, $revoked[0]->payload['share_id']);
    }

    public function test_non_admin_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        $share = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $member,
            'tokenHash' => hash('sha256', 'token-non-admin'),
            'expiryDatetime' => new \DateTimeImmutable('+1 day'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/shares/' . $share->id,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez être administrateur pour effectuer cette action',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous devez être administrateur pour effectuer cette action',
        ]);
    }

    public function test_unknown_share_returns_404(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/shares/00000000-0000-0000-0000-000000000000',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Lien de partage introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Lien de partage introuvable',
        ]);
    }
}
