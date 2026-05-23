<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class AgendaEntryGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_band_entries_only(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => 'Préparer le set list',
            'location' => 'Studio',
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();
        AgendaEntryFactory::new([
            'bandSpace' => $otherBand,
            'title' => 'Autre groupe',
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
                    '@type' => 'AgendaEntry',
                    'id' => $entry->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Répétition',
                    'description' => 'Préparer le set list',
                    'location' => 'Studio',
                    'event_datetime' => '2026-06-15T20:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'recurrence_frequency' => null,
                    'recurrence_until_date' => null,
                    'recurrence_monthly_mode' => null,
                    'creator_id' => $user->id,
                    'creator_username' => $user->username,
                    'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_list_orders_by_event_datetime_asc(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $later = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Plus tard',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-08-20 20:00:00', new \DateTimeZone('UTC')),
        ])->create();
        $earlier = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Plus tôt',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-05 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $earlier->id,
                    '@type' => 'AgendaEntry',
                    'id' => $earlier->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Plus tôt',
                    'description' => null,
                    'location' => null,
                    'event_datetime' => '2026-06-05T20:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'recurrence_frequency' => null,
                    'recurrence_until_date' => null,
                    'recurrence_monthly_mode' => null,
                    'creator_id' => $user->id,
                    'creator_username' => $user->username,
                    'creation_datetime' => $earlier->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $later->id,
                    '@type' => 'AgendaEntry',
                    'id' => $later->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Plus tard',
                    'description' => null,
                    'location' => null,
                    'event_datetime' => '2026-08-20T20:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'recurrence_frequency' => null,
                    'recurrence_until_date' => null,
                    'recurrence_monthly_mode' => null,
                    'creator_id' => $user->id,
                    'creator_username' => $user->username,
                    'creation_datetime' => $later->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_list_exposes_populated_recurrence_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Réunion mensuelle',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-05 19:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Monthly,
            'recurrenceMonthlyMode' => \App\Enum\BandSpace\AgendaRecurrenceMonthlyMode::ByWeekday,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            '@type' => 'Collection',
            'member' => [
                [
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
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_list_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
