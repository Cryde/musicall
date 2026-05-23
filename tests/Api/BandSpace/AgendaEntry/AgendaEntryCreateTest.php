<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\Agenda\ValidRecurrence;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class AgendaEntryCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_agenda_entry_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Répétition générale',
                'eventDatetime' => '2026-06-15T20:00:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entries = $repo->findByBandSpace($bandSpace);
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition générale',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_created', $activities[0]->type);
        $this->assertSame(['title' => 'Répétition générale'], $activities[0]->payload);
        $this->assertSame($user->id, $activities[0]->actor?->id);
    }

    public function test_create_agenda_entry_with_all_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Concert au Zenith',
                'description' => 'Apporter le matériel à 18h',
                'location' => 'Zenith de Paris',
                'eventDatetime' => '2026-07-20T21:30:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entries = $repo->findByBandSpace($bandSpace);
        $entry = $entries[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Concert au Zenith',
            'description' => 'Apporter le matériel à 18h',
            'location' => 'Zenith de Paris',
            'event_datetime' => '2026-07-20T21:30:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_agenda_entry_with_end_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Concert',
                'eventDatetime' => '2026-07-20T20:00:00+00:00',
                'endDatetime' => '2026-07-20T23:00:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entries = $repo->findByBandSpace($bandSpace);
        $entry = $entries[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-07-20T20:00:00+00:00',
            'end_datetime' => '2026-07-20T23:00:00+00:00',
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_agenda_entry_rejects_end_before_start(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Concert',
                'eventDatetime' => '2026-07-20T20:00:00+00:00',
                'endDatetime' => '2026-07-20T19:00:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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

    public function test_create_agenda_entry_all_day_single_day(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Off',
                'eventDatetime' => '2026-08-15',
                'isAllDay' => true,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entries = $repo->findByBandSpace($bandSpace);
        $entry = $entries[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Off',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-08-15T00:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => true,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_agenda_entry_all_day_multi_day_normalizes_time(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Hellfest',
                'eventDatetime' => '2026-06-19T15:30:00+02:00',
                'endDatetime' => '2026-06-21T18:00:00+02:00',
                'isAllDay' => true,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entries = $repo->findByBandSpace($bandSpace);
        $entry = $entries[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Hellfest',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-19T00:00:00+00:00',
            'end_datetime' => '2026-06-21T00:00:00+00:00',
            'is_all_day' => true,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_agenda_entry_validation_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => '',
                'eventDatetime' => '2026-06-15T20:00:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_agenda_entry_validation_missing_event_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            ['title' => 'Sans date'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_weekly_recurrence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Répétition hebdo',
                'eventDatetime' => '2026-01-04T18:00:00+00:00',
                'recurrenceFrequency' => 'weekly',
                'recurrenceUntilDate' => '2026-06-30',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entry = $repo->findByBandSpace($bandSpace)[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition hebdo',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-01-04T18:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2026-06-30',
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_monthly_by_weekday_recurrence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Réunion mensuelle',
                'eventDatetime' => '2026-01-05T19:00:00+00:00', // first Monday of Jan 2026
                'recurrenceFrequency' => 'monthly',
                'recurrenceMonthlyMode' => 'by_weekday',
                'recurrenceUntilDate' => '2026-12-31',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entry = $repo->findByBandSpace($bandSpace)[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Réunion mensuelle',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-01-05T19:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'monthly',
            'recurrence_until_date' => '2026-12-31',
            'recurrence_monthly_mode' => 'by_weekday',
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_yearly_recurrence_for_birthday(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Anniversaire batteur',
                'eventDatetime' => '2026-08-15T00:00:00+00:00',
                'isAllDay' => true,
                'recurrenceFrequency' => 'yearly',
                'recurrenceUntilDate' => '2030-12-31',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $entry = $repo->findByBandSpace($bandSpace)[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Anniversaire batteur',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-08-15T00:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => true,
            'recurrence_frequency' => 'yearly',
            'recurrence_until_date' => '2030-12-31',
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_recurrence_rejects_missing_until(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Sans fin',
                'eventDatetime' => '2026-01-04T18:00:00+00:00',
                'recurrenceFrequency' => 'weekly',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::MISSING_UNTIL_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_until_date',
                    'message' => 'Veuillez spécifier une date de fin de récurrence.',
                    'code' => ValidRecurrence::MISSING_UNTIL_CODE,
                ],
            ],
            'detail' => 'recurrence_until_date: Veuillez spécifier une date de fin de récurrence.',
            'type' => '/validation_errors/' . ValidRecurrence::MISSING_UNTIL_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_until_date: Veuillez spécifier une date de fin de récurrence.',
        ]);
    }

    public function test_create_recurrence_rejects_monthly_without_mode(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Mensuel mais sans mode',
                'eventDatetime' => '2026-01-15T18:00:00+00:00',
                'recurrenceFrequency' => 'monthly',
                'recurrenceUntilDate' => '2026-12-31',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::MISSING_MONTHLY_MODE_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_monthly_mode',
                    'message' => 'Veuillez préciser le mode de récurrence mensuelle (par date ou par jour de la semaine).',
                    'code' => ValidRecurrence::MISSING_MONTHLY_MODE_CODE,
                ],
            ],
            'detail' => 'recurrence_monthly_mode: Veuillez préciser le mode de récurrence mensuelle (par date ou par jour de la semaine).',
            'type' => '/validation_errors/' . ValidRecurrence::MISSING_MONTHLY_MODE_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_monthly_mode: Veuillez préciser le mode de récurrence mensuelle (par date ou par jour de la semaine).',
        ]);
    }

    public function test_create_recurrence_rejects_until_before_event(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Récurrence dans le passé',
                'eventDatetime' => '2026-06-15T18:00:00+00:00',
                'recurrenceFrequency' => 'weekly',
                'recurrenceUntilDate' => '2026-03-01',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::UNTIL_BEFORE_EVENT_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_until_date',
                    'message' => 'La date de fin de récurrence doit être postérieure ou égale au premier événement.',
                    'code' => ValidRecurrence::UNTIL_BEFORE_EVENT_CODE,
                ],
            ],
            'detail' => 'recurrence_until_date: La date de fin de récurrence doit être postérieure ou égale au premier événement.',
            'type' => '/validation_errors/' . ValidRecurrence::UNTIL_BEFORE_EVENT_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_until_date: La date de fin de récurrence doit être postérieure ou égale au premier événement.',
        ]);
    }

    public function test_create_recurrence_rejects_until_beyond_5_years(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Trop loin',
                'eventDatetime' => '2026-01-04T18:00:00+00:00',
                'recurrenceFrequency' => 'weekly',
                'recurrenceUntilDate' => '2031-01-05',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::UNTIL_TOO_FAR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_until_date',
                    'message' => 'La date de fin de récurrence ne peut pas dépasser 5 ans après le premier événement.',
                    'code' => ValidRecurrence::UNTIL_TOO_FAR_CODE,
                ],
            ],
            'detail' => 'recurrence_until_date: La date de fin de récurrence ne peut pas dépasser 5 ans après le premier événement.',
            'type' => '/validation_errors/' . ValidRecurrence::UNTIL_TOO_FAR_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_until_date: La date de fin de récurrence ne peut pas dépasser 5 ans après le premier événement.',
        ]);
    }

    public function test_create_recurrence_rejects_invalid_frequency(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Bad frequency',
                'eventDatetime' => '2026-01-04T18:00:00+00:00',
                'recurrenceFrequency' => 'hourly',
                'recurrenceUntilDate' => '2026-12-31',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::INVALID_FREQUENCY_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_frequency',
                    'message' => 'Fréquence de récurrence invalide.',
                    'code' => ValidRecurrence::INVALID_FREQUENCY_CODE,
                ],
            ],
            'detail' => 'recurrence_frequency: Fréquence de récurrence invalide.',
            'type' => '/validation_errors/' . ValidRecurrence::INVALID_FREQUENCY_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_frequency: Fréquence de récurrence invalide.',
        ]);
    }

    public function test_create_recurrence_rejects_invalid_until_format(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Bad until',
                'eventDatetime' => '2026-01-04T18:00:00+00:00',
                'recurrenceFrequency' => 'weekly',
                'recurrenceUntilDate' => 'not-a-date',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::INVALID_UNTIL_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_until_date',
                    'message' => 'Date de fin de récurrence invalide.',
                    'code' => ValidRecurrence::INVALID_UNTIL_CODE,
                ],
            ],
            'detail' => 'recurrence_until_date: Date de fin de récurrence invalide.',
            'type' => '/validation_errors/' . ValidRecurrence::INVALID_UNTIL_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_until_date: Date de fin de récurrence invalide.',
        ]);
    }

    public function test_create_recurrence_rejects_invalid_monthly_mode(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Bad monthly mode',
                'eventDatetime' => '2026-01-15T18:00:00+00:00',
                'recurrenceFrequency' => 'monthly',
                'recurrenceMonthlyMode' => 'every_other_tuesday',
                'recurrenceUntilDate' => '2026-12-31',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidRecurrence::INVALID_MONTHLY_MODE_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'recurrence_monthly_mode',
                    'message' => 'Mode de récurrence mensuelle invalide.',
                    'code' => ValidRecurrence::INVALID_MONTHLY_MODE_CODE,
                ],
            ],
            'detail' => 'recurrence_monthly_mode: Mode de récurrence mensuelle invalide.',
            'type' => '/validation_errors/' . ValidRecurrence::INVALID_MONTHLY_MODE_CODE,
            'title' => 'An error occurred',
            'description' => 'recurrence_monthly_mode: Mode de récurrence mensuelle invalide.',
        ]);
    }

    public function test_create_agenda_entry_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [
                'title' => 'Forbidden',
                'eventDatetime' => '2026-06-15T20:00:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
