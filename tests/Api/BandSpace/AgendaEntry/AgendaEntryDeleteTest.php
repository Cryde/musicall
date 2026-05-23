<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class AgendaEntryDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_agenda_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert annulé',
        ])->create();
        $entryId = $entry->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNull($repo->findOneByIdAndBandSpace($entryId, $bandSpace));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_deleted', $activities[0]->type);
        $this->assertSame(['title' => 'Concert annulé'], $activities[0]->payload);
    }

    public function test_delete_recurring_entry_removes_the_row(): void
    {
        // Aggregator expansions are derived from the entity at read time, so once the row is
        // gone, no occurrence can be expanded. The expansion paths themselves are covered by
        // AgendaGetCollectionTest — this case just confirms the recurring shape doesn't change
        // the delete semantics.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new \DateTimeImmutable('2026-01-04 18:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new \DateTimeImmutable('2026-02-28'),
        ])->create();
        $entryId = $entry->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNull($repo->findOneByIdAndBandSpace($entryId, $bandSpace));
    }

    public function test_delete_agenda_entry_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $entry = AgendaEntryFactory::new(['bandSpace' => $bandSpace, 'creator' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_agenda_entry_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/00000000-0000-0000-0000-000000000000',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
