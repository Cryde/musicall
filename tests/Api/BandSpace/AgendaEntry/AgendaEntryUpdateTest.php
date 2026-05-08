<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AgendaEntryUpdateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_update_agenda_entry_title_and_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Ancien titre',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            [
                'title' => 'Nouveau titre',
                'eventDatetime' => '2026-06-20T18:30:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->_real()->id,
            'band_space_id' => $bandSpace->_real()->id,
            'title' => 'Nouveau titre',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-20T18:30:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Agenda, $entry->_real()->id);
        $types = array_map(fn($a) => $a->type, $activities);
        $this->assertEqualsCanonicalizing(['title_changed', 'event_datetime_changed'], $types);
    }

    public function test_update_agenda_entry_partial_keeps_other_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'location' => 'Salle A',
            'description' => 'Description initiale',
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['location' => 'Salle B'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->_real()->id,
            'band_space_id' => $bandSpace->_real()->id,
            'title' => 'Concert',
            'description' => 'Description initiale',
            'location' => 'Salle B',
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Agenda, $entry->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('location_changed', $activities[0]->type);
        $this->assertSame(['from' => 'Salle A', 'to' => 'Salle B'], $activities[0]->payload);
    }

    public function test_update_set_end_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['endDatetime' => '2026-06-15T23:00:00+00:00'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->_real()->id,
            'band_space_id' => $bandSpace->_real()->id,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => '2026-06-15T23:00:00+00:00',
            'is_all_day' => false,
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Agenda, $entry->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('end_datetime_changed', $activities[0]->type);
        $this->assertSame(
            ['from' => null, 'to' => '2026-06-15T23:00:00+00:00'],
            $activities[0]->payload,
        );
    }

    public function test_update_clear_end_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-06-15 23:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['endDatetime' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->_real()->id,
            'band_space_id' => $bandSpace->_real()->id,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Agenda, $entry->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('end_datetime_changed', $activities[0]->type);
        $this->assertSame(
            ['from' => '2026-06-15T23:00:00+00:00', 'to' => null],
            $activities[0]->payload,
        );
    }

    public function test_update_rejects_end_before_start(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['endDatetime' => '2026-06-15T19:00:00+00:00'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@id' => '/api/validation_errors/778b7ae0-84d3-481a-9dec-35fdb64b1d78',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_datetime',
                    'message' => 'La fin doit être postérieure au début',
                    'code' => '778b7ae0-84d3-481a-9dec-35fdb64b1d78',
                ],
            ],
            'detail' => 'end_datetime: La fin doit être postérieure au début',
            'type' => '/validation_errors/778b7ae0-84d3-481a-9dec-35fdb64b1d78',
            'title' => 'An error occurred',
            '@context' => '/api/contexts/ConstraintViolation',
            'description' => 'end_datetime: La fin doit être postérieure au début',
        ]);
    }

    public function test_update_toggle_all_day_normalizes_time(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Tournée',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 14:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-06-17 22:30:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['isAllDay' => true],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->_real()->id,
            'band_space_id' => $bandSpace->_real()->id,
            'title' => 'Tournée',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T00:00:00+00:00',
            'end_datetime' => '2026-06-17T00:00:00+00:00',
            'is_all_day' => true,
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Agenda, $entry->_real()->id);
        $types = array_map(fn($a) => $a->type, $activities);
        $this->assertEqualsCanonicalizing(
            ['is_all_day_changed', 'event_datetime_changed', 'end_datetime_changed'],
            $types,
        );
    }

    public function test_update_no_change_records_no_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['title' => 'Concert'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Agenda, $entry->_real()->id);
        $this->assertCount(0, $activities);
    }

    public function test_update_agenda_entry_validation_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['title' => ''],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_update_agenda_entry_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $entry = AgendaEntryFactory::new(['bandSpace' => $bandSpace, 'creator' => $owner])->create();

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
