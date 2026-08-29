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
use Symfony\Component\HttpFoundation\Response;
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
            'sourceId' => \Ramsey\Uuid\Uuid::uuid4(),
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
            'sourceId' => \Ramsey\Uuid\Uuid::fromString('7e57d004-2b97-0e7a-b45f-5387367791cd'),
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
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
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
