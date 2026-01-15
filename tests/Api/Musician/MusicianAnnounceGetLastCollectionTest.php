<?php

namespace App\Tests\Api\Musician;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianAnnounceGetLastCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_last_musician_announces(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $style1 = StyleFactory::new()->asRock()->create();
        $style2 = StyleFactory::new()->asPop()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();
        $instrument2 = InstrumentFactory::new()->asGuitar()->create();

        $user1Announce1 = MusicianAnnounceFactory::new()->create([
            'author' => $user1,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'instrument' => $instrument1,
            'locationName' => 'Mons',
            'note' => 'note announce 1',
            'type' => 1, // type musician
            'styles' => [$style1]
        ]);

        $user1Announce2 = MusicianAnnounceFactory::new()->create([
            'author' => $user1,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'instrument' => $instrument2,
            'locationName' => 'Paris',
            'note' => 'note announce 2',
            'type' => 2, // type band
            'styles' => [$style1, $style2]
        ]);

        $this->client->request('GET', '/api/musician_announces/last');
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/last',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/musician_announces/' . $user1Announce2->_real()->getId(),
                    '@type' => 'MusicianAnnounce',
                    'id' => $user1Announce2->_real()->getId(),
                    'creation_datetime' => '2022-01-02T02:03:04+00:00',
                    'type' => 2,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $instrument2->getId(),
                        'musician_name' => 'Guitariste',
                    ],
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'id' => $style1->getId(),
                            'name' => 'Rock',
                        ],
                        [
                            '@type' => 'Style',
                            'id' => $style2->getId(),
                            'name' => 'Pop',
                        ],
                    ],
                    'location_name' => 'Paris',
                    'note' => 'note announce 2',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $user1->_real()->getId(),
                        'username' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
                [
                    '@id' => '/api/musician_announces/' . $user1Announce1->_real()->getId(),
                    '@type' => 'MusicianAnnounce',
                    'id' => $user1Announce1->_real()->getId(),
                    'creation_datetime' => '2020-01-02T02:03:04+00:00',
                    'type' => 1,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $instrument1->getId(),
                        'musician_name' => 'Batteur',
                    ],
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'id' => $style1->getId(),
                            'name' => 'Rock',
                        ],
                    ],
                    'location_name' => 'Mons',
                    'note' => 'note announce 1',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $user1->_real()->getId(),
                        'username' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
            ],
            'totalItems' => 2,
        ]);
    }
}
