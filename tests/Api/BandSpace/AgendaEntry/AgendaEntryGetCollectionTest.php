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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AgendaEntryGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $entry->_real()->id,
                    '@type' => 'AgendaEntry',
                    'id' => $entry->_real()->id,
                    'band_space_id' => $bandSpace->_real()->id,
                    'title' => 'Répétition',
                    'description' => 'Préparer le set list',
                    'location' => 'Studio',
                    'event_datetime' => '2026-06-15T20:00:00+00:00',
                    'creator_id' => $user->_real()->id,
                    'creator_username' => $user->_real()->username,
                    'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $earlier->_real()->id,
                    '@type' => 'AgendaEntry',
                    'id' => $earlier->_real()->id,
                    'band_space_id' => $bandSpace->_real()->id,
                    'title' => 'Plus tôt',
                    'description' => null,
                    'location' => null,
                    'event_datetime' => '2026-06-05T20:00:00+00:00',
                    'creator_id' => $user->_real()->id,
                    'creator_username' => $user->_real()->username,
                    'creation_datetime' => $earlier->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries/' . $later->_real()->id,
                    '@type' => 'AgendaEntry',
                    'id' => $later->_real()->id,
                    'band_space_id' => $bandSpace->_real()->id,
                    'title' => 'Plus tard',
                    'description' => null,
                    'location' => null,
                    'event_datetime' => '2026-08-20T20:00:00+00:00',
                    'creator_id' => $user->_real()->id,
                    'creator_username' => $user->_real()->username,
                    'creation_datetime' => $later->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_list_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda-entries',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
