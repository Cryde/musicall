<?php

namespace App\Tests\Api\Announce;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class AnnounceSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

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

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/musicians/search',
            [
                'type' => '1',
                'instrument' => $guitar->id,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 12,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/' . $announce2->id,
                    '@type' => 'AnnounceMusician',
                    'id' => $announce2->id,
                    'location_name' => 'Lyon',
                    'note' => 'Guitariste rock cherche groupe 2',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author2->id,
                        'username' => 'olivia',
                        'has_musician_profile' => false,
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Guitariste',
                    ],
                    'type' => 1,
                    'styles' => [
                        ['@type' => 'Style', 'name' => 'Rock'],
                    ],
                ],
                [
                    '@id' => '/api/announce_musicians/' . $announce1->id,
                    '@type' => 'AnnounceMusician',
                    'id' => $announce1->id,
                    'location_name' => 'Paris',
                    'note' => 'Guitariste rock cherche groupe',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author1->id,
                        'username' => 'philip',
                        'has_musician_profile' => false,
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Guitariste',
                    ],
                    'type' => 1,
                    'styles' => [
                        ['@type' => 'Style', 'name' => 'Rock'],
                        ['@type' => 'Style', 'name' => 'Pop'],
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/musicians/search?instrument=' . $guitar->id . '&type=1',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musicians/search{?type,instrument,styles}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    ['@type' => 'IriTemplateMapping', 'variable' => 'type', 'property' => 'type', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'instrument', 'property' => 'instrument', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'styles', 'property' => 'styles', 'required' => false],
                ],
            ],
        ]);
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

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/musicians/search',
            [
                'type' => '1',
                'instrument' => $drum->id,
                'latitude' => '48.8566',
                'longitude' => '2.3522'
            ]
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertCount(2, $response['member']);
        $this->assertSame('Paris', $response['member'][0]['location_name']);
        $this->assertEquals(0.0, $response['member'][0]['distance']);
        $this->assertSame('Marseille', $response['member'][1]['location_name']);
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

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/musicians/search',
            [
                'type' => '1',
                'instrument' => $guitar->id,
                'styles' => [$rock->id],
            ]
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertCount(1, $response['member']);
        $this->assertSame($announce1->id, $response['member'][0]['id']);
    }

    public function test_search_musicians_without_parameters_returns_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/musicians/search',
        );

        $this->assertResponseIsSuccessful();
    }
}
