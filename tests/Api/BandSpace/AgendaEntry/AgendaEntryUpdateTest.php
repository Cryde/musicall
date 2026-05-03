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
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
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
            'creator_id' => $user->_real()->id,
            'creator_username' => $user->_real()->username,
            'creation_datetime' => $entry->_real()->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
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
