<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_search_returns_one_hit_per_type_grouped_in_type_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $agendaEntry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Session mixage',
            'eventDatetime' => new \DateTimeImmutable('2026-06-15 20:00:00'),
        ])->create();

        $taskCategory = TaskCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio'])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'category' => $taskCategory,
            'title' => 'Relancer le mixage',
        ])->create();

        $parentNote = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Répétitions',
        ])->create();
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'parent' => $parentNote,
            'title' => 'Notes de mixage',
        ])->create();

        $folder = BandSpaceFolderFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'name' => 'Maquettes',
        ])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'folder' => $folder,
            'originalName' => 'mixage-final.wav',
        ])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Set mixage'])->create();

        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Mixage nocturne',
            'tonality' => 'Am',
        ])->create();

        $financeCategory = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio'])->create();
        $financeEntry = FinanceEntryFactory::new([
            'category' => $financeCategory,
            'label' => 'Séance de mixage',
            'date' => new \DateTime('2026-05-04 00:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 7,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=agenda-' . $agendaEntry->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'agenda-' . $agendaEntry->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'agenda',
                    'resource_id' => (string) $agendaEntry->id,
                    'title' => 'Session mixage',
                    'subtitle' => '15/06/2026',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $task->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $task->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $task->id,
                    'title' => 'Relancer le mixage',
                    'subtitle' => 'Studio',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=note-' . $note->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'note-' . $note->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'note',
                    'resource_id' => (string) $note->id,
                    'title' => 'Notes de mixage',
                    'subtitle' => 'Répétitions',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=file-' . $file->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'file-' . $file->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'file',
                    'resource_id' => (string) $file->id,
                    'title' => 'mixage-final.wav',
                    'subtitle' => 'Maquettes',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=setlist-' . $setlist->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'setlist-' . $setlist->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'setlist',
                    'resource_id' => (string) $setlist->id,
                    'title' => 'Set mixage',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $song->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $song->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $song->id,
                    'title' => 'Mixage nocturne',
                    'subtitle' => 'Am',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=finance-' . $financeEntry->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'finance-' . $financeEntry->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'finance',
                    'resource_id' => (string) $financeEntry->id,
                    'title' => 'Séance de mixage',
                    'subtitle' => '04/05/2026',
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_search_matches_a_substring_whatever_the_case(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'RENDEZ-VOUS'])->create();
        SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Autre chose'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=dez',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $song->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $song->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $song->id,
                    'title' => 'RENDEZ-VOUS',
                    'subtitle' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=dez',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_a_query_below_two_characters_opens_on_the_recent_items(): void
    {
        // Below two characters there is nothing to search for, so the palette opens on what the band
        // touched last instead (#1046).
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mixage nocturne', 'tonality' => 'Am'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=m',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $song->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $song->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $song->id,
                    'title' => 'Mixage nocturne',
                    'subtitle' => 'Am',
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=m',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_no_query_opens_on_the_recent_items(): void
    {
        // Below two characters there is nothing to search for, so the palette opens on what the band
        // touched last instead (#1046).
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mixage nocturne', 'tonality' => 'Am'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $song->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $song->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $song->id,
                    'title' => 'Mixage nocturne',
                    'subtitle' => 'Am',
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_search_ignores_archived_records(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $archivedAt = new \DateTimeImmutable('2026-01-05 10:00:00');
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Mixage archivé',
            'archiveDatetime' => $archivedAt,
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'mixage.wav',
            'archiveDatetime' => $archivedAt,
        ])->create();
        SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Set mixage',
            'archiveDatetime' => $archivedAt,
        ])->create();
        SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Mixage nocturne',
            'archiveDatetime' => $archivedAt,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_search_ignores_records_of_another_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBandSpace, 'user' => $user])->create();

        SongFactory::new(['bandSpace' => $otherBandSpace, 'title' => 'Mixage nocturne'])->create();
        $otherCategory = FinanceCategoryFactory::new(['bandSpace' => $otherBandSpace, 'name' => 'Studio'])->create();
        FinanceEntryFactory::new(['category' => $otherCategory, 'label' => 'Séance de mixage'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_search_hides_a_personal_finance_entry_of_another_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $otherMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $otherUser,
        ])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio'])->create();
        $bandEntry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage du groupe',
            'scope' => FinanceEntryScope::Band,
            'date' => new \DateTime('2026-05-04 00:00:00'),
        ])->create();
        FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage perso',
            'scope' => FinanceEntryScope::Personal,
            'member' => $otherMembership,
            'date' => new \DateTime('2026-05-06 00:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=finance-' . $bandEntry->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'finance-' . $bandEntry->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'finance',
                    'resource_id' => (string) $bandEntry->id,
                    'title' => 'Mixage du groupe',
                    'subtitle' => '04/05/2026',
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_search_caps_each_type_at_five_hits(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Seven matching songs and nothing else: the per type cap is what trims them, not the total.
        foreach (range(1, 7) as $index) {
            SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mixage ' . $index])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(5, $response['totalItems']);
        $this->assertSame(
            ['Mixage 1', 'Mixage 2', 'Mixage 3', 'Mixage 4', 'Mixage 5'],
            array_column($response['member'], 'title'),
        );
    }

    public function test_search_spreads_the_total_cap_across_the_types_that_match(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $financeCategory = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio'])->create();

        // Five of every type, so all seven hit the per type cap and the total of 35 has to be trimmed.
        foreach (range(1, 5) as $index) {
            AgendaEntryFactory::new([
                'bandSpace' => $bandSpace,
                'creator' => $user,
                'title' => 'Mixage ' . $index,
            ])->create();
            TaskFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'title' => 'Mixage ' . $index,
            ])->create();
            BandSpaceNoteFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'title' => 'Mixage ' . $index,
            ])->create();
            BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => 'mixage-' . $index . '.wav',
            ])->create();
            SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Mixage ' . $index])->create();
            SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mixage ' . $index])->create();
            FinanceEntryFactory::new(['category' => $financeCategory, 'label' => 'Mixage ' . $index])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();

        $this->assertSame(20, $response['totalItems']);

        // Round robin: six types keep three rows and the seventh keeps the two the budget still had.
        // Truncating in type order would instead have returned agenda, tasks, notes and files only.
        $countByType = array_count_values(array_column($response['member'], 'type'));
        $this->assertSame(
            ['agenda' => 3, 'task' => 3, 'note' => 3, 'file' => 3, 'setlist' => 3, 'song' => 3, 'finance' => 2],
            $countByType,
        );
    }

    public function test_search_is_forbidden_for_a_non_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
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

    public function test_search_is_forbidden_for_a_member_who_left(): void
    {
        $departedUser = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $departedUser,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($departedUser);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
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

    public function test_search_on_an_unknown_band_space_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/6a1b2c3d-4e5f-4a6b-8c9d-0e1f2a3b4c5d/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Band Space introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Band Space introuvable',
        ]);
    }

    public function test_search_is_unauthorized_without_a_session(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mixage',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /** Five in total across every kind, newest first on the latest of creation and edit (#1046). */
    public function test_recents_merge_every_kind_newest_first_and_stop_at_five(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $oldest = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Vieille tâche', 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Intro', 'tonality' => 'E', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Compte rendu', 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Samedi', 'creationDatetime' => new \DateTime('2026-09-03 10:00:00')])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Louer le van', 'creationDatetime' => new \DateTime('2026-09-04 10:00:00')])->create();
        $agenda = AgendaEntryFactory::new(['bandSpace' => $bandSpace, 'creator' => $user, 'title' => 'Concert', 'eventDatetime' => new \DateTimeImmutable('2026-10-10 20:00:00'), 'creationDatetime' => new \DateTime('2026-09-05 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 5,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=agenda-' . $agenda->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'agenda-' . $agenda->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'agenda',
                    'resource_id' => (string) $agenda->id,
                    'title' => 'Concert',
                    'subtitle' => '10/10/2026',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $task->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $task->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $task->id,
                    'title' => 'Louer le van',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=setlist-' . $setlist->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'setlist-' . $setlist->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'setlist',
                    'resource_id' => (string) $setlist->id,
                    'title' => 'Samedi',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=note-' . $note->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'note-' . $note->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'note',
                    'resource_id' => (string) $note->id,
                    'title' => 'Compte rendu',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $song->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $song->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $song->id,
                    'title' => 'Intro',
                    'subtitle' => 'E',
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** An edit counts as much as a creation: an old item touched today comes first. */
    public function test_an_edited_old_item_comes_before_a_newer_untouched_one(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $edited = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Vieux morceau', 'tonality' => 'D', 'creationDatetime' => new \DateTime('2025-01-01 10:00:00'), 'updateDatetime' => new \DateTime('2026-09-20 10:00:00')])->create();
        $untouched = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Tâche récente', 'creationDatetime' => new \DateTime('2026-09-10 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $edited->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $edited->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $edited->id,
                    'title' => 'Vieux morceau',
                    'subtitle' => 'D',
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $untouched->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $untouched->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $untouched->id,
                    'title' => 'Tâche récente',
                    'subtitle' => null,
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_recents_leave_archived_items_out(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $live = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Au répertoire', 'tonality' => 'G', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Archivé', 'creationDatetime' => new \DateTime('2026-09-10 10:00:00'), 'archiveDatetime' => new \DateTimeImmutable('2026-09-11 10:00:00')])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Tâche archivée', 'creationDatetime' => new \DateTime('2026-09-12 10:00:00'), 'archiveDatetime' => new \DateTime('2026-09-12 11:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=song-' . $live->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'song-' . $live->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'song',
                    'resource_id' => (string) $live->id,
                    'title' => 'Au répertoire',
                    'subtitle' => 'G',
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** A chat image or voice note is a file, but one per message: it would fill the list after any conversation. */
    public function test_recents_leave_chat_media_out(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $contract = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'contrat.pdf', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $voiceNote = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'note-vocale.m4a', 'creationDatetime' => new \DateTime('2026-09-10 10:00:00')])->create();
        BandSpaceFileAttachmentFactory::new(['bandSpaceFile' => $voiceNote, 'sourceType' => 'message', 'attachedBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=file-' . $contract->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'file-' . $contract->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'file',
                    'resource_id' => (string) $contract->id,
                    'title' => 'contrat.pdf',
                    'subtitle' => null,
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** The one visibility rule a band space has: another member's personal entry never shows. */
    public function test_recents_hide_another_members_personal_finance_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $other = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com'])])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $bandEntry = FinanceEntryFactory::new(['category' => $category, 'label' => 'Location du local', 'date' => new \DateTime('2026-09-01'), 'scope' => FinanceEntryScope::Band, 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        FinanceEntryFactory::new(['category' => $category, 'label' => 'Cordes perso', 'date' => new \DateTime('2026-09-02'), 'scope' => FinanceEntryScope::Personal, 'member' => $other, 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=finance-' . $bandEntry->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'finance-' . $bandEntry->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'finance',
                    'resource_id' => (string) $bandEntry->id,
                    'title' => 'Location du local',
                    'subtitle' => '01/09/2026',
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Editing a recurrence bumps every entry it regenerates: only the latest of them may take a slot,
     * or one edit would fill the whole list with the same label.
     */
    public function test_recents_show_one_entry_per_recurrence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $recurrence = FinanceRecurrenceFactory::new(['category' => $category, 'label' => 'Loyer'])->create();
        $regeneratedAt = new \DateTime('2026-09-20 10:00:00');
        $entries = [];
        foreach (range(1, 6) as $month) {
            $entries[] = FinanceEntryFactory::new([
                'category' => $category,
                'label' => 'Loyer',
                'recurrence' => $recurrence,
                'date' => new \DateTime(sprintf('2026-%02d-01', $month)),
                'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
                'updateDatetime' => $regeneratedAt,
            ])->create();
        }
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Intro', 'tonality' => 'E', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $member = $this->getResponseAsArray()['member'];
        // Which of the six carries the recurrence is a tie on the date, broken by id.
        $this->assertCount(2, $member);
        $this->assertSame('finance', $member[0]['type']);
        $this->assertSame('Loyer', $member[0]['title']);
        $this->assertContains($member[0]['resource_id'], array_map(static fn ($entry): string => (string) $entry->id, $entries));
        $this->assertSame('song-' . $song->id, $member[1]['id']);
    }

    /**
     * One occurrence of a series reassigned to another member's personal scope must not hide the rest
     * of the series from everyone else: only what this member can see may outrank an entry.
     */
    public function test_a_recurrence_whose_newest_entry_is_another_members_still_shows_for_the_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $other = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com'])])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $recurrence = FinanceRecurrenceFactory::new(['category' => $category, 'label' => 'Loyer'])->create();
        $bandEntry = FinanceEntryFactory::new(['category' => $category, 'label' => 'Loyer', 'recurrence' => $recurrence, 'scope' => FinanceEntryScope::Band, 'date' => new \DateTime('2026-08-01'), 'creationDatetime' => new \DateTime('2026-08-01 10:00:00')])->create();
        FinanceEntryFactory::new(['category' => $category, 'label' => 'Loyer perso', 'recurrence' => $recurrence, 'scope' => FinanceEntryScope::Personal, 'member' => $other, 'date' => new \DateTime('2026-09-01'), 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?type=finance',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=finance-' . $bandEntry->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'finance-' . $bandEntry->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'finance',
                    'resource_id' => (string) $bandEntry->id,
                    'title' => 'Loyer',
                    'subtitle' => '01/08/2026',
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?type=finance',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** A kind picked with no query still opens on five, the recents cap: the twenty is for a search. */
    public function test_a_type_filter_on_an_empty_query_stops_at_five(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tasks = [];
        foreach (range(1, 6) as $day) {
            $tasks[] = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Tâche ' . $day, 'creationDatetime' => new \DateTime(sprintf('2026-09-%02d 10:00:00', $day))])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?type=task',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        // Days 6 down to 2: the oldest is the one left out.
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 5,
            'member' => array_map(static fn ($task): array => [
                '@id' => '/api/band_space_search_results/id=task-' . $task->id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'BandSpaceSearchResult',
                'id' => 'task-' . $task->id,
                'band_space_id' => (string) $bandSpace->id,
                'type' => 'task',
                'resource_id' => (string) $task->id,
                'title' => $task->title,
                'subtitle' => null,
            ], array_reverse(array_slice($tasks, 1))),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?type=task',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** One kind picked with a query: only that kind, and up to twenty of it rather than five. */
    public function test_a_type_filter_searches_one_kind_with_a_higher_cap(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $songs = [];
        foreach (range(1, 7) as $index) {
            $songs[] = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mix ' . $index, 'tonality' => null])->create();
        }
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Mix final'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?q=mix&type=song',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        // Seven, past the five a kind gets when every kind shares the budget, and no task.
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 7,
            'member' => array_map(static fn ($song): array => [
                '@id' => '/api/band_space_search_results/id=song-' . $song->id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'BandSpaceSearchResult',
                'id' => 'song-' . $song->id,
                'band_space_id' => (string) $bandSpace->id,
                'type' => 'song',
                'resource_id' => (string) $song->id,
                'title' => $song->title,
                'subtitle' => null,
            ], $songs),
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=mix&type=song',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** One kind picked with no query: its five most recent. */
    public function test_a_type_filter_on_an_empty_query_opens_on_that_kinds_recent_items(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Louer le van', 'creationDatetime' => new \DateTime('2026-09-04 10:00:00')])->create();
        SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Plus récent mais morceau', 'creationDatetime' => new \DateTime('2026-09-10 10:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?type=task',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $task->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $task->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $task->id,
                    'title' => 'Louer le van',
                    'subtitle' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?type=task',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/band_spaces/' . $bandSpace->id . '/search{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_an_unknown_type_is_refused_on_its_parameter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/search?type=concert',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => 'Type de résultat inconnu',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'type: Type de résultat inconnu',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'type: Type de résultat inconnu',
        ]);
    }
}
