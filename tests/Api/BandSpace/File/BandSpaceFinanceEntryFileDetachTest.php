<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFinanceEntryFileDetachTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_detach_clears_attachment_keeps_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new(['category' => $category, 'scope' => FinanceEntryScope::Band])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::fromString($entry->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/files/' . $file->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $fileRepo->find($file->id);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);

        $attachmentRepo = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepo->findOneByFileAndSource($reloaded, 'finance', $entry->id));
    }

    /** The file is the band's, the personal entry's label is not (#1146). */
    public function test_detaching_from_a_personal_entry_does_not_name_it_in_the_file_feed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new(['category' => $category, 'scope' => FinanceEntryScope::Personal, 'member' => $membership])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::fromString($entry->id),
            'attachedBy' => $user,
        ]);
        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpaceId . '/finance/entries/' . $entry->id . '/files/' . $file->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $payloads = array_map(
            static fn ($activity): ?array => $activity->payload,
            self::getContainer()->get(BandSpaceActivityRepository::class)->findBy([
                'bandSpace' => $bandSpaceId,
                'module' => BandSpaceModule::File,
                'type' => 'detached',
            ]),
        );
        $this->assertSame([['source_type' => 'finance', 'source_id' => (string) $entry->id, 'source_label' => null]], $payloads);
    }

    public function test_detach_with_archive_query_archives_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new(['category' => $category, 'scope' => FinanceEntryScope::Band])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::fromString($entry->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/files/' . $file->id . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->id);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->archiveDatetime);
    }

    public function test_detach_personal_entry_by_non_owner_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'scope' => FinanceEntryScope::Personal,
            'member' => $ownerMembership,
        ])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::fromString($entry->id),
            'attachedBy' => $owner,
        ]);

        $this->client->loginUser($other);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/files/' . $file->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez modifier que vos propres entrées personnelles',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous ne pouvez modifier que vos propres entrées personnelles',
        ]);
    }

    public function test_detach_file_not_attached_to_entry_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new(['category' => $category, 'scope' => FinanceEntryScope::Band])->create();
        $otherEntry = FinanceEntryFactory::new(['category' => $category, 'scope' => FinanceEntryScope::Band])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::fromString($otherEntry->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/files/' . $file->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le fichier n'est pas attaché à cette entrée",
            'status' => 404,
            'type' => '/errors/404',
            'description' => "Le fichier n'est pas attaché à cette entrée",
        ]);
    }
}
