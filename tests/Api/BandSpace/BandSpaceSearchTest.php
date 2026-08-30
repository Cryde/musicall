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
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
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
        ]);
    }

    public function test_search_below_two_characters_returns_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mixage nocturne'])->create();

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
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?q=m',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_search_without_a_query_returns_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mixage nocturne'])->create();

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
            'totalItems' => 0,
            'member' => [],
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
}
