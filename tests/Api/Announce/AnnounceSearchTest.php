<?php

namespace App\Tests\Api\Announce;

use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AnnounceSearchTest extends ApiTestCase
{
    use ResetDatabase, Factories;

    public function test_search_musicians_with_filters(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $author1 = UserFactory::new()->asBaseUser()->create(['username' => 'philip', 'email' => 'email']);
        $author2 = UserFactory::new()->asBaseUser()->create(['username' => 'olivia', 'email' => 'olivia@mail.com']);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock, $pop])
            ->asMusician()
            ->create([
                'author' => $author1,
                'locationName' => 'Paris',
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'note' => 'Guitariste rock cherche groupe',
                'creationDatetime' => new \DateTime('2020-01-01T00:00:00+00:00')
            ]);

        $announce2 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create([
                'author' => $author2,
                'locationName' => 'Lyon',
                'latitude' => '45.7640',
                'longitude' => '4.8357',
                'note' => 'Guitariste rock cherche groupe 2',
                'creationDatetime' => new \DateTime('2022-01-01T00:00:00+00:00')
            ]);

        // This announce should NOT be returned (different instrument)
        MusicianAnnounceFactory::new()
            ->withInstrument($drum)
            ->withStyles([$rock])
            ->asMusician()
            ->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/musicians/search',
            [
                'type' => '1',
                'instrument' => $guitar->getId(),
            ]
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('/api/contexts/AnnounceMusician', $response['@context']);
        $this->assertEquals('/api/musicians/search', $response['@id']);
        $this->assertEquals('Collection', $response['@type']);

        // Verify only 2 members returned (the guitar ones)
        $this->assertCount(2, $response['member']);

        // First result (newest) should be Lyon
        $this->assertEquals($announce2->getId(), $response['member'][0]['id']);
        $this->assertEquals('Lyon', $response['member'][0]['location_name']);
        $this->assertEquals('olivia', $response['member'][0]['user']['username']);
        $this->assertEquals('Guitariste', $response['member'][0]['instrument']['name']);

        // Second result should be Paris
        $this->assertEquals($announce1->getId(), $response['member'][1]['id']);
        $this->assertEquals('Paris', $response['member'][1]['location_name']);
        $this->assertEquals('philip', $response['member'][1]['user']['username']);
    }

    public function test_search_musicians_with_latitude_longitude(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $author1 = UserFactory::new()->asBaseUser()->create(['username' => 'drummer1', 'email' => 'drummer1@mail.com']);
        $author2 = UserFactory::new()->asBaseUser()->create(['username' => 'drummer2', 'email' => 'drummer2@mail.com']);
        $drum = InstrumentFactory::new()->asDrum()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        MusicianAnnounceFactory::new()
            ->withInstrument($drum)
            ->withStyles([$metal])
            ->asMusician()
            ->create([
                'author' => $author1,
                'locationName' => 'Paris',
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'note' => 'Batteur metal à Paris',
                'creationDatetime' => new \DateTime('2020-01-01T00:00:00+00:00')
            ]);

        MusicianAnnounceFactory::new()
            ->withInstrument($drum)
            ->withStyles([$metal])
            ->asMusician()
            ->create([
                'author' => $author2,
                'locationName' => 'Marseille',
                'latitude' => '43.2965',
                'longitude' => '5.3698',
                'note' => 'Batteur metal à Marseille',
                'creationDatetime' => new \DateTime('2022-01-01T00:00:00+00:00')
            ]);

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/musicians/search',
            [
                'type' => '1',
                'instrument' => $drum->getId(),
                'latitude' => '48.8566',
                'longitude' => '2.3522'
            ]
        );

        $this->assertResponseIsSuccessful();

        // With coordinates, results should be ordered by distance (Paris first, then Marseille)
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['member']);

        // First result should be Paris (closest to search coordinates)
        $this->assertEquals('Paris', $response['member'][0]['location_name']);
        $this->assertArrayHasKey('distance', $response['member'][0]);
        $this->assertEquals(0.0, $response['member'][0]['distance']);

        // Second result should be Marseille (further away)
        $this->assertEquals('Marseille', $response['member'][1]['location_name']);
        $this->assertArrayHasKey('distance', $response['member'][1]);
        $this->assertGreaterThan(0, $response['member'][1]['distance']);
    }

    public function test_search_musicians_with_styles_filter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'olivia', 'email' => 'olivia@email.com']);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock, $pop])
            ->asMusician()
            ->create(['author' => $author, 'note' => 'some notes']);

        // This announce should NOT be returned (different style)
        MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$metal])
            ->asMusician()
            ->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/musicians/search',
            [
                'type' => '1',
                'instrument' => $guitar->getId(),
                'styles' => [$rock->getId()],
            ]
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response['member']);
        $this->assertEquals($announce1->getId(), $response['member'][0]['id']);
    }

    public function test_search_musicians_without_parameters_returns_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/musicians/search',
        );

        $this->assertResponseIsSuccessful();
    }
}
