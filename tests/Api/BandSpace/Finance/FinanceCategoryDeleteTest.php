<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\Uid\Uuid;
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceCategoryDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_category(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'To Delete',
        ])->create();
        $categoryId = (string) $category->id;

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $categoryId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $repo = self::getContainer()->get(FinanceCategoryRepository::class);
        $this->assertNull($repo->find($categoryId));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $categoryId);
        $this->assertCount(1, $activities);
        $this->assertSame('category_deleted', $activities[0]->type);
        $this->assertSame(['name' => 'To Delete'], $activities[0]->payload);
    }

    /**
     * `finance_entry.category_id` is ON DELETE CASCADE and the category holds no inverse collection,
     * so its entries vanish in the database without Doctrine ever loading them. Their attachments
     * have to go too: an attachment naming an entry that no longer exists locks its file forever,
     * because the delete endpoint refuses an attached file and the detach endpoint 404s.
     */
    public function test_delete_category_detaches_the_files_of_the_entries_it_cascades(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio'])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 12000,
            'date' => new \DateTime('2026-03-01'),
        ])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin])->create();
        $attachment = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::fromString((string) $entry->id),
            'attachedBy' => $admin,
        ]);

        $categoryId = (string) $category->id;
        $fileId = (string) $file->id;
        $attachmentId = (string) $attachment->id;

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $categoryId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $attachmentRepository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepository->find($attachmentId));

        $fileRepository = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloadedFile = $fileRepository->find($fileId);
        $this->assertNotNull($reloadedFile, 'The file outlives the category: it belongs to the library');
        $this->assertNull($reloadedFile->archiveDatetime);

        // The condition BandSpaceFileDeleteProcessor guards on: anything here and the file is stuck.
        $this->assertSame([], $attachmentRepository->findByFile($reloadedFile));
    }

    public function test_delete_category_as_non_admin_member_returns_403(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'plain_member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Protected Category',
        ])->create();
        $categoryId = (string) $category->id;

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $categoryId);

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

        // Category not deleted, no activity recorded.
        $this->assertNotNull(self::getContainer()->get(FinanceCategoryRepository::class)->find($categoryId));
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $categoryId));
    }

    public function test_delete_category_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Protected Category',
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_category_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner_user', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Protected Category',
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
