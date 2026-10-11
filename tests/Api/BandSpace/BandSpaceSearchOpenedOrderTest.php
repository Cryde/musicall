<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\BandSpaceOpenedItemFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The palette's « Ouverts par moi » order (#1047): the same recents, sorted by the caller's own opens.
 */
#[ResetDatabase]
class BandSpaceSearchOpenedOrderTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /** Newest open first, whatever the band edited since: an item opened but never touched comes first. */
    public function test_the_opened_order_lists_my_opens_newest_first(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $agenda = AgendaEntryFactory::new(['bandSpace' => $bandSpace, 'creator' => $user, 'title' => 'Concert', 'eventDatetime' => new \DateTimeImmutable('2026-10-10 20:00:00'), 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Louer le van', 'creationDatetime' => new \DateTime('2026-01-02 10:00:00')])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Compte rendu', 'creationDatetime' => new \DateTime('2026-01-03 10:00:00')])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Samedi', 'creationDatetime' => new \DateTime('2026-01-04 10:00:00')])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Intro', 'tonality' => 'E', 'creationDatetime' => new \DateTime('2026-01-05 10:00:00')])->create();
        $openedLongAgo = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Ouverte il y a longtemps', 'creationDatetime' => new \DateTime('2026-01-06 10:00:00')])->create();
        // The band's latest change, never opened by this member.
        TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Créée hier', 'creationDatetime' => new \DateTime('2026-10-10 10:00:00')])->create();

        $this->opened($membership, BandSpaceSearchResultType::Task, $openedLongAgo->id, '2026-10-01 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Song, $song->id, '2026-10-02 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Setlist, $setlist->id, '2026-10-03 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Note, $note->id, '2026-10-04 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Task, $task->id, '2026-10-05 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Agenda, $agenda->id, '2026-10-06 09:00:00');

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened', [], ['HTTP_ACCEPT' => 'application/ld+json']);

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
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened',
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

    /** The history is private: another member's opens never reach this list, an admin's included. */
    public function test_another_members_opens_are_never_listed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $admin = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']), 'role' => Role::Admin])->create();
        $tasks = [];
        foreach (['Un', 'Deux', 'Trois', 'Quatre', 'Cinq'] as $index => $title) {
            $tasks[] = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => $title, 'creationDatetime' => new \DateTime('2026-01-0' . ($index + 1) . ' 10:00:00')])->create();
            $this->opened($membership, BandSpaceSearchResultType::Task, $tasks[$index]->id, '2026-10-0' . ($index + 1) . ' 09:00:00');
        }
        $theirs = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Ouverte par le batteur', 'creationDatetime' => new \DateTime('2026-01-09 10:00:00')])->create();
        $this->opened($admin, BandSpaceSearchResultType::Note, $theirs->id, '2026-10-09 09:00:00');
        [$one, $two, $three, $four, $five] = $tasks;

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 5,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $five->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $five->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $five->id,
                    'title' => 'Cinq',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $four->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $four->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $four->id,
                    'title' => 'Quatre',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $three->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $three->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $three->id,
                    'title' => 'Trois',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $two->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $two->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $two->id,
                    'title' => 'Deux',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $one->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $one->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $one->id,
                    'title' => 'Un',
                    'subtitle' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened',
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

    /** Resolved against the live tables: deleted, archived and foreign ids drop out, a rename shows. */
    public function test_an_item_gone_since_it_was_opened_drops_out(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Renommée depuis', 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        $archived = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Archivée', 'creationDatetime' => new \DateTime('2026-01-02 10:00:00'), 'archiveDatetime' => new \DateTime('2026-01-03 10:00:00')])->create();
        $elsewhere = TaskFactory::new(['bandSpace' => BandSpaceFactory::new(), 'createdBy' => $user, 'title' => 'Dans un autre groupe', 'creationDatetime' => new \DateTime('2026-01-04 10:00:00')])->create();

        $this->opened($membership, BandSpaceSearchResultType::Note, $note->id, '2026-10-01 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Task, $archived->id, '2026-10-02 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Task, $elsewhere->id, '2026-10-03 09:00:00');
        // Deleted since: nothing left to point at.
        $this->opened($membership, BandSpaceSearchResultType::Setlist, '9c8b7a6d-5e4f-4a3b-9c2d-1e0f9a8b7c6d', '2026-10-04 09:00:00');

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=note-' . $note->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'note-' . $note->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'note',
                    'resource_id' => (string) $note->id,
                    'title' => 'Renommée depuis',
                    'subtitle' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened',
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

    /** Its id in the table is not enough: another member's personal entry stays theirs. */
    public function test_another_members_personal_finance_entry_never_shows_even_once_opened(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $other = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com'])])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $bandEntry = FinanceEntryFactory::new(['category' => $category, 'label' => 'Location du local', 'date' => new \DateTime('2026-09-01'), 'scope' => FinanceEntryScope::Band, 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        // Opened while it was a band entry, moved to the bassist's personal scope since.
        $theirs = FinanceEntryFactory::new(['category' => $category, 'label' => 'Cordes perso', 'date' => new \DateTime('2026-09-02'), 'scope' => FinanceEntryScope::Personal, 'member' => $other, 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();
        $this->opened($membership, BandSpaceSearchResultType::Finance, $theirs->id, '2026-10-01 09:00:00');

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened', [], ['HTTP_ACCEPT' => 'application/ld+json']);

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
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened',
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

    /** A short history is topped up with the band's latest changes, without listing anything twice. */
    public function test_a_short_history_is_topped_up_with_the_bands_recents(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $opened = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Ouverte', 'creationDatetime' => new \DateTime('2026-09-03 10:00:00')])->create();
        $older = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Plus ancienne', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $newer = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Plus récente', 'creationDatetime' => new \DateTime('2026-09-05 10:00:00')])->create();
        $this->opened($membership, BandSpaceSearchResultType::Task, $opened->id, '2026-10-01 09:00:00');

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/band_space_search_results/id=task-' . $opened->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'task-' . $opened->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'task',
                    'resource_id' => (string) $opened->id,
                    'title' => 'Ouverte',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=setlist-' . $newer->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'setlist-' . $newer->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'setlist',
                    'resource_id' => (string) $newer->id,
                    'title' => 'Plus récente',
                    'subtitle' => null,
                ],
                [
                    '@id' => '/api/band_space_search_results/id=note-' . $older->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'note-' . $older->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'note',
                    'resource_id' => (string) $older->id,
                    'title' => 'Plus ancienne',
                    'subtitle' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened',
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

    public function test_the_kind_filter_applies_to_the_opened_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Louer le van', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Compte rendu', 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();
        $this->opened($membership, BandSpaceSearchResultType::Task, $task->id, '2026-10-01 09:00:00');
        $this->opened($membership, BandSpaceSearchResultType::Note, $note->id, '2026-10-02 09:00:00');

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened&type=task', [], ['HTTP_ACCEPT' => 'application/ld+json']);

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
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened&type=task',
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

    /** A query sorts its hits by kind: the order only shapes the list shown before typing. */
    public function test_the_order_is_ignored_once_something_is_typed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Louer le van', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Van : état des lieux', 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();
        $this->opened($membership, BandSpaceSearchResultType::Note, $note->id, '2026-10-02 09:00:00');

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=opened&q=van', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceSearchResult',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/search',
            '@type' => 'Collection',
            'totalItems' => 2,
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
                [
                    '@id' => '/api/band_space_search_results/id=note-' . $note->id . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'BandSpaceSearchResult',
                    'id' => 'note-' . $note->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'type' => 'note',
                    'resource_id' => (string) $note->id,
                    'title' => 'Van : état des lieux',
                    'subtitle' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/search?order=opened&q=van',
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

    public function test_an_unknown_order_is_refused_on_its_parameter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/search?order=popular', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'order',
                    'message' => 'Ordre inconnu',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'order: Ordre inconnu',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'order: Ordre inconnu',
        ]);
    }

    private function opened(BandSpaceMembership $membership, BandSpaceSearchResultType $type, string $resourceId, string $openedAt): void
    {
        BandSpaceOpenedItemFactory::new(['membership' => $membership, 'type' => $type, 'resourceId' => $resourceId, 'openedDatetime' => new \DateTimeImmutable($openedAt)])->create();
    }
}
