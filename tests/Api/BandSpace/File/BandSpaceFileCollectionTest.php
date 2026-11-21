<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\Filter\BandSpaceFileFilter;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_files_in_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf'])->create();
        $version = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $file, 'mimeType' => 'application/pdf'])->create();
        $file->currentVersion = $version;
        \Zenstruck\Foundry\Persistence\save($file);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame('rider.pdf', $response['member'][0]['original_name']);
    }

    /**
     * folder_path is the most expensive field on the resource. BandSpaceFolder::$parent is a plain
     * ManyToOne and so LAZY, and the collection hydrates only the file's own folder, so building the
     * path per file costs a SELECT per level per folder. #918 put that field on every row of the space
     * wide listings, which is what made it hot.
     *
     * Three files, each at the bottom of its own three deep chain, so nothing is shared and the walk
     * has nine ancestors to fetch. The whole tree is read in one query now, and that is what is pinned
     * here rather than a total: the total hides the trade, one query up front against N lazy loads.
     */
    public function test_list_reads_the_whole_folder_tree_in_one_query_however_deep_it_is(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $files = [];
        $paths = [];
        // Pinned datetimes so the date desc default puts the rows in a known order, and a chain per
        // file so no ancestor is already in the identity map when the next row is built.
        foreach ([['Live', '2026', 'Paris'], ['Studio', 'Demos', 'Avril'], ['Admin', 'Contrats', 'Signés']] as $index => $names) {
            $parent = null;
            $path = [];
            foreach ($names as $name) {
                $parent = BandSpaceFolderFactory::new([
                    'bandSpace' => $bandSpace,
                    'createdBy' => $user,
                    'name' => $name,
                    'parent' => $parent,
                ])->create();
                $path[] = ['id' => $parent->id, 'name' => $name];
            }

            $files[] = BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'folder' => $parent,
                'originalName' => 'chain-' . $index . '.pdf',
                'creationDatetime' => new \DateTime(sprintf('2026-0%d-01T01:01:01+00:00', 3 - $index)),
            ])->create();
            $paths['chain-' . $index . '.pdf'] = $path;
        }

        $this->client->loginUser($user);
        $this->client->enableProfiler();
        // A real request starts with an empty identity map. The factories above left every folder
        // managed, which would hide the very lazy loads this test exists to count.
        self::getContainer()->get('doctrine')->getManager()->clear();
        // The debug holder lives as long as the connection, so everything the factories above just
        // wrote is already in it. Emptied here, the collector answers for the request alone.
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(3, $response['totalItems']);
        $this->assertSame(
            [
                ['chain-0.pdf', $paths['chain-0.pdf']],
                ['chain-1.pdf', $paths['chain-1.pdf']],
                ['chain-2.pdf', $paths['chain-2.pdf']],
            ],
            array_map(
                static fn (array $member): array => [$member['original_name'], $member['folder_path']],
                $response['member'],
            ),
        );

        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to assert the query count.');
        $folderReads = array_filter(
            $profile->getCollector('db')->getQueries()['default'] ?? [],
            static fn (array $query): bool => str_contains((string) $query['sql'], 'FROM band_space_folder'),
        );
        $this->assertCount(
            1,
            $folderReads,
            'The tree must be read once for the whole space, not one SELECT per ancestor per file.',
        );
    }

    public function test_list_filtered_by_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Setlists'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $folder, 'originalName' => 'in-folder.pdf'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'in-root.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?folder_id=' . $folder->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('in-folder.pdf', $response['member'][0]['original_name']);
    }

    /**
     * The root of the tree is not the whole space: a file in a folder belongs to that folder, and an
     * attachment belongs to its virtual folder, so neither shows here. Only a standalone file with no
     * folder does. All three exist in this space, so the listing has to exclude two of them.
     */
    public function test_list_filtered_by_root_excludes_foldered_files_and_attachments(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Contrats'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $folder, 'originalName' => 'in-folder.pdf'])->create();

        // No folder, but attached to a note, so the Notes virtual folder already lists it.
        $attached = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'attached.pdf'])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attached,
            'sourceType' => 'note',
            'sourceId' => Uuid::v7()->toRfc4122(),
            'attachedBy' => $user,
        ]);

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'in-root.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?folder_id=root', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('in-root.pdf', $response['member'][0]['original_name']);
    }

    /** No folder_id at all still means the whole space, which is what the flat listing needs. */
    public function test_list_without_folder_filter_still_returns_every_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Contrats'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $folder, 'originalName' => 'in-folder.pdf'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'in-root.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->getResponseAsArray()['totalItems']);
    }

    public function test_list_filtered_by_tag(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tag = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'masters'])->create();
        $tagged = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'tagged.pdf'])->create();
        $tagged->tags->add($tag);
        \Zenstruck\Foundry\Persistence\save($tagged);

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'untagged.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?tag_id=' . $tag->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('tagged.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_finance_entry_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $entryId = '11111111-1111-1111-1111-111111111111';
        $otherEntryId = '22222222-2222-2222-2222-222222222222';

        $matchingFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'matching.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $matchingFile,
            'sourceType' => 'finance',
            'sourceId' => $entryId,
            'attachedBy' => $user,
        ]);
        $otherFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'other-entry.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $otherFile,
            'sourceType' => 'finance',
            'sourceId' => $otherEntryId,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files?source=finance&finance_entry_id=' . $entryId,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('matching.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_source_manual_excludes_attached(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'standalone.pdf'])->create();
        $attachedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'attached-to-task.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attachedFile,
            'sourceType' => 'task',
            'sourceId' => '7e57d004-2b97-0e7a-b45f-5387367791cd',
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?source=manual', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('standalone.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_query_substring(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'master-2025.flac'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?query=master', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('master-2025.flac', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_mime_prefix(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $audio = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'master.flac'])->create();
        $audioVersion = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $audio, 'mimeType' => 'audio/flac'])->create();
        $audio->currentVersion = $audioVersion;
        \Zenstruck\Foundry\Persistence\save($audio);

        $pdf = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf'])->create();
        $pdfVersion = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $pdf, 'mimeType' => 'application/pdf'])->create();
        $pdf->currentVersion = $pdfVersion;
        \Zenstruck\Foundry\Persistence\save($pdf);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?mime=audio/', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('master.flac', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_uploader(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->asBaseUser()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $alice, 'originalName' => 'alice.pdf'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $bob, 'originalName' => 'bob.pdf'])->create();

        $this->client->loginUser($alice);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?uploader_id=' . $bob->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('bob.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_paginated(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        for ($i = 0; $i < 60; $i++) {
            BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => sprintf('file-%02d.pdf', $i),
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?page=2', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(60, $response['totalItems']);
        $this->assertCount(10, $response['member']);
    }

    public function test_list_honours_a_client_page_size(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->createFiles($bandSpace, $user, [
            'oldest.pdf' => '2026-01-01 10:00:00',
            'middle.pdf' => '2026-01-02 10:00:00',
            'newest.pdf' => '2026-01-03 10:00:00',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?itemsPerPage=2', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(3, $response['totalItems']);
        $this->assertSame(['newest.pdf', 'middle.pdf'], array_column($response['member'], 'original_name'));
    }

    /**
     * The tags fetch join turns one file into one row per tag, so paging over the joined rows rather
     * than over distinct files handed back short pages and stepped over the overflow on the next one.
     */
    public function test_pages_do_not_drop_files_carrying_several_tags(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tags = [];
        foreach (['masters', 'live', 'demos'] as $tagName) {
            $tags[] = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => $tagName])->create();
        }

        foreach ($this->createFiles($bandSpace, $user, [
            'file-a.pdf' => '2026-03-01 10:00:00',
            'file-b.pdf' => '2026-03-02 10:00:00',
            'file-c.pdf' => '2026-03-03 10:00:00',
            'file-d.pdf' => '2026-03-04 10:00:00',
        ]) as $file) {
            foreach ($tags as $tag) {
                $file->tags->add($tag);
            }
            \Zenstruck\Foundry\Persistence\save($file);
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?itemsPerPage=2&page=1', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $firstPage = $this->getResponseAsArray();
        $this->assertSame(4, $firstPage['totalItems']);
        $this->assertSame(['file-d.pdf', 'file-c.pdf'], array_column($firstPage['member'], 'original_name'));

        // The api firewall is stateless, so an authenticated session does not survive into a second
        // request. Later pages are read through the repository the provider itself calls, which is
        // also where the fix lives: without the Paginator the tag join cuts the page short here.
        $this->assertSame(['file-b.pdf', 'file-a.pdf'], $this->pageOfNames($bandSpace, 2, 2));
    }

    /**
     * One page of the live file list, straight from the repository the collection provider calls.
     *
     * @return list<string> the original names, in the order the query returned them
     */
    private function pageOfNames(BandSpace $bandSpace, int $limit, int $offset): array
    {
        $bandSpace = self::getContainer()->get(BandSpaceRepository::class)->find($bandSpace->id);
        $files = self::getContainer()->get(BandSpaceFileRepository::class)->findByBandSpace(
            $bandSpace,
            new BandSpaceFileFilter(limit: $limit, offset: $offset),
        );

        return array_map(static fn(BandSpaceFile $file): string => $file->originalName, $files);
    }

    /**
     * The trash is the sharp edge: app:band-space:purge destroys what a member cannot reach to restore.
     */
    public function test_trash_pages_past_the_first_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        foreach ([
            'trashed-a.pdf' => '2026-05-01 10:00:00',
            'trashed-b.pdf' => '2026-05-02 10:00:00',
            'trashed-c.pdf' => '2026-05-03 10:00:00',
        ] as $name => $createdAt) {
            BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => $name,
                'creationDatetime' => new \DateTime($createdAt),
                'archiveDatetime' => new \DateTimeImmutable($createdAt),
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?archived=true&itemsPerPage=2&page=2', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(3, $response['totalItems']);
        $this->assertSame(['trashed-a.pdf'], array_column($response['member'], 'original_name'));
    }

    public function test_list_excludes_archived(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live.pdf'])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'archived.pdf',
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('live.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Tu n'es pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Tu n'es pas membre de ce Band Space",
        ]);
    }

    /**
     * The Tablatures module (#1430). A Guitar Pro file is recognised by its name: GP3 to GPX are stored
     * as application/octet-stream and a .gp as application/zip, so the zip below differs only by name.
     */
    public function test_format_guitar_pro_lists_every_guitar_pro_format_across_folders(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $songs = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Morceaux'])->create();
        $year = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => '2026', 'parent' => $songs])->create();

        $gp3 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'intro.gp3', 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        $gp4 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $songs, 'originalName' => 'Solo.GP4', 'creationDatetime' => new \DateTime('2026-01-02 10:00:00')])->create();
        $gp5 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $year, 'originalName' => 'riff.gp5', 'creationDatetime' => new \DateTime('2026-01-03 10:00:00')])->create();
        $gpx = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'ballade.gpx', 'creationDatetime' => new \DateTime('2026-01-04 10:00:00')])->create();
        $gp = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $songs, 'originalName' => 'nouvelle.gp', 'creationDatetime' => new \DateTime('2026-01-05 10:00:00')])->create();
        $gpVersion = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $gp, 'createdBy' => $user, 'mimeType' => 'application/zip', 'size' => 2048])->create();
        $gp->currentVersion = $gpVersion;
        \Zenstruck\Foundry\Persistence\save($gp);

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf', 'creationDatetime' => new \DateTime('2026-01-06 10:00:00')])->create();
        $zip = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $songs, 'originalName' => 'stems.zip', 'creationDatetime' => new \DateTime('2026-01-07 10:00:00')])->create();
        $zipVersion = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $zip, 'createdBy' => $user, 'mimeType' => 'application/zip', 'size' => 4096])->create();
        $zip->currentVersion = $zipVersion;
        \Zenstruck\Foundry\Persistence\save($zip);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $createdBy = ['id' => $user->id, 'username' => 'alice', 'display_name' => 'alice', 'profile_picture_url' => null];
        $filesUrl = '/api/band_spaces/' . $bandSpace->id . '/files/';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFile',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/files',
            '@type' => 'Collection',
            'totalItems' => 5,
            'member' => [
                [
                    '@id' => $filesUrl . $gp->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $gp->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'nouvelle.gp',
                    'size' => 2048,
                    'mime_type' => 'application/zip',
                    'folder_id' => $songs->id,
                    'folder_path' => [['id' => $songs->id, 'name' => 'Morceaux']],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => $gpVersion->id,
                    'current_version_number' => 1,
                    'version_count' => 1,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $gp->id . '/download',
                    'creation_datetime' => '2026-01-05T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
                [
                    '@id' => $filesUrl . $gpx->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $gpx->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'ballade.gpx',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => null,
                    'folder_path' => [],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $gpx->id . '/download',
                    'creation_datetime' => '2026-01-04T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
                [
                    '@id' => $filesUrl . $gp5->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $gp5->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'riff.gp5',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => $year->id,
                    'folder_path' => [['id' => $songs->id, 'name' => 'Morceaux'], ['id' => $year->id, 'name' => '2026']],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $gp5->id . '/download',
                    'creation_datetime' => '2026-01-03T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
                [
                    '@id' => $filesUrl . $gp4->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $gp4->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'Solo.GP4',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => $songs->id,
                    'folder_path' => [['id' => $songs->id, 'name' => 'Morceaux']],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $gp4->id . '/download',
                    'creation_datetime' => '2026-01-02T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
                [
                    '@id' => $filesUrl . $gp3->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $gp3->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'intro.gp3',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => null,
                    'folder_path' => [],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $gp3->id . '/download',
                    'creation_datetime' => '2026-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_format_guitar_pro_leaves_out_the_trash_and_other_spaces(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $otherSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $user])->create();

        $live = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live.gp5', 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'trashed.gp5',
            'creationDatetime' => new \DateTime('2026-01-02 10:00:00'),
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();
        BandSpaceFileFactory::new(['bandSpace' => $otherSpace, 'createdBy' => $user, 'originalName' => 'elsewhere.gp5', 'creationDatetime' => new \DateTime('2026-01-03 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFile',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/files',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $live->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $live->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'live.gp5',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => null,
                    'folder_path' => [],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => ['id' => $user->id, 'username' => 'alice', 'display_name' => 'alice', 'profile_picture_url' => null],
                    'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $live->id . '/download',
                    'creation_datetime' => '2026-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /** The Tablatures list puts the latest change on top: a file's update date wins over its upload date. */
    public function test_sort_updated_puts_the_latest_change_first(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $tag = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'live'])->create();

        $reworked = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'reworked.gp',
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
            'updateDatetime' => new \DateTime('2026-03-01 10:00:00'),
            'tags' => new ArrayCollection([$tag]),
        ])->create();
        $untouched = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'untouched.gp5', 'creationDatetime' => new \DateTime('2026-02-01 10:00:00')])->create();
        $older = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'older.gpx',
            'creationDatetime' => new \DateTime('2026-01-10 10:00:00'),
            'updateDatetime' => new \DateTime('2026-01-20 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro&sort=updated', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $createdBy = ['id' => $user->id, 'username' => 'alice', 'display_name' => 'alice', 'profile_picture_url' => null];
        $filesUrl = '/api/band_spaces/' . $bandSpace->id . '/files/';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFile',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/files',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => $filesUrl . $reworked->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $reworked->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'reworked.gp',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => null,
                    'folder_path' => [],
                    'tags' => [['id' => $tag->id, 'name' => 'live', 'color_hex' => null]],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $reworked->id . '/download',
                    'creation_datetime' => '2026-01-01T10:00:00+00:00',
                    'update_datetime' => '2026-03-01T10:00:00+00:00',
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
                [
                    '@id' => $filesUrl . $untouched->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $untouched->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'untouched.gp5',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => null,
                    'folder_path' => [],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $untouched->id . '/download',
                    'creation_datetime' => '2026-02-01T10:00:00+00:00',
                    'update_datetime' => null,
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
                [
                    '@id' => $filesUrl . $older->id,
                    '@type' => 'BandSpaceFile',
                    'id' => $older->id,
                    'band_space_id' => $bandSpace->id,
                    'original_name' => 'older.gpx',
                    'size' => null,
                    'mime_type' => null,
                    'folder_id' => null,
                    'folder_path' => [],
                    'tags' => [],
                    'attachments' => [],
                    'current_version_id' => null,
                    'current_version_number' => null,
                    'version_count' => 0,
                    'created_by' => $createdBy,
                    'download_url' => $filesUrl . $older->id . '/download',
                    'creation_datetime' => '2026-01-10T10:00:00+00:00',
                    'update_datetime' => '2026-01-20T10:00:00+00:00',
                    'archive_datetime' => null,
                    'purge_datetime' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro&sort=updated',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_format_refuses_an_unknown_value(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?format=pdf', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'format', 'message' => 'Format de fichier inconnu', 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => 'format: Format de fichier inconnu',
            'description' => 'format: Format de fichier inconnu',
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_format_guitar_pro_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member, 'originalName' => 'song.gp'])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?format=guitar_pro', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Tu n'es pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Tu n'es pas membre de ce Band Space",
        ]);
    }

    /**
     * Creation datetimes are pinned rather than left to the faker: they decide which file lands on
     * which page, and the default sort is on them.
     *
     * @param array<string, string> $creationDatetimeByName
     *
     * @return BandSpaceFile[] in the order given
     */
    private function createFiles(BandSpace $bandSpace, User $user, array $creationDatetimeByName): array
    {
        $files = [];
        foreach ($creationDatetimeByName as $name => $createdAt) {
            $files[] = BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => $name,
                'creationDatetime' => new \DateTime($createdAt),
            ])->create();
        }

        return $files;
    }
}
