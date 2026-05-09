<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFinanceEntryFileAttachTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_attach_band_scoped_entry_happy_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Studio invoice',
            'scope' => FinanceEntryScope::Band,
        ])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'invoice.txt', 'text/plain', null, true);

        $this->client->loginUser($user->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/finance/entries/' . $entry->_real()->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $files = $repo->findBy(['bandSpace' => $bandSpace->_real()]);
        $this->assertCount(1, $files);

        /** @var BandSpaceFile $file */
        $file = $files[0];
        $this->assertSame('finance', $file->attachedSourceType);
        $this->assertSame($entry->_real()->id, (string) $file->attachedSourceId);
    }

    public function test_attach_personal_entry_by_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'scope' => FinanceEntryScope::Personal,
            'member' => $membership,
        ])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($owner->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/finance/entries/' . $entry->_real()->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function test_attach_personal_entry_by_non_owner_returns_403(): void
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

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($other->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/finance/entries/' . $entry->_real()->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
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

    public function test_attach_entry_in_other_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $otherBand = BandSpaceFactory::new()->create();
        $otherCategory = FinanceCategoryFactory::new(['bandSpace' => $otherBand])->create();
        $foreignEntry = FinanceEntryFactory::new([
            'category' => $otherCategory,
            'scope' => FinanceEntryScope::Band,
        ])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/finance/entries/' . $foreignEntry->_real()->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Entrée introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Entrée introuvable',
        ]);
    }

    public function test_attach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'scope' => FinanceEntryScope::Band,
        ])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($other->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/finance/entries/' . $entry->_real()->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
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
