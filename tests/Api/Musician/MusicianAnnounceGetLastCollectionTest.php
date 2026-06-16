<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MusicianAnnounceGetLastCollectionTest extends ApiTestCase
{
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
                    '@id' => '/api/musician_announces/' . $user1Announce2->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $user1Announce2->id,
                    'creation_datetime' => '2022-01-02T02:03:04+00:00',
                    'type' => 2,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $instrument2->id,
                        'musician_name' => 'Guitariste',
                    ],
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'id' => $style1->id,
                            'name' => 'Rock',
                        ],
                        [
                            '@type' => 'Style',
                            'id' => $style2->id,
                            'name' => 'Pop',
                        ],
                    ],
                    'location_name' => 'Paris',
                    'note' => 'note announce 2',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $user1->id,
                        'username' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
                [
                    '@id' => '/api/musician_announces/' . $user1Announce1->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $user1Announce1->id,
                    'creation_datetime' => '2020-01-02T02:03:04+00:00',
                    'type' => 1,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $instrument1->id,
                        'musician_name' => 'Batteur',
                    ],
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'id' => $style1->id,
                            'name' => 'Rock',
                        ],
                    ],
                    'location_name' => 'Mons',
                    'note' => 'note announce 1',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $user1->id,
                        'username' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_last_announces_eager_load_relations_without_n_plus_one(): void
    {
        // Three distinct authors so a per-row author lazy-load would balloon the query
        // count; one of them owns a musician profile to exercise that projection branch.
        $authorWithProfile = UserFactory::new()->asBaseUser()->create(['username' => 'author_musician', 'email' => 'author_musician@email.com']);
        MusicianProfileFactory::new()->create(['user' => $authorWithProfile]);
        $author2 = UserFactory::new()->asBaseUser()->create(['username' => 'author_two', 'email' => 'author_two@email.com']);
        $author3 = UserFactory::new()->asBaseUser()->create(['username' => 'author_three', 'email' => 'author_three@email.com']);

        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $newest = MusicianAnnounceFactory::new()->create([
            'author' => $author3,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2023-03-03T03:03:03+00:00'),
            'instrument' => $guitar,
            'locationName' => 'Lyon',
            'note' => 'newest announce',
            'type' => 2,
            'styles' => [$rock, $pop],
        ]);
        $middle = MusicianAnnounceFactory::new()->create([
            'author' => $author2,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-02-02T02:02:02+00:00'),
            'instrument' => $drum,
            'locationName' => 'Nantes',
            'note' => 'middle announce',
            'type' => 1,
            'styles' => [],
        ]);
        $oldest = MusicianAnnounceFactory::new()->create([
            'author' => $authorWithProfile,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-01T01:01:01+00:00'),
            'instrument' => $drum,
            'locationName' => 'Brest',
            'note' => 'oldest announce',
            'type' => 1,
            'styles' => [$rock],
        ]);

        $this->client->enableProfiler();
        $this->client->request('GET', '/api/musician_announces/last');

        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/last',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/musician_announces/' . $newest->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $newest->id,
                    'creation_datetime' => '2023-03-03T03:03:03+00:00',
                    'type' => 2,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $guitar->id,
                        'musician_name' => 'Guitariste',
                    ],
                    'styles' => [
                        ['@type' => 'Style', 'id' => $rock->id, 'name' => 'Rock'],
                        ['@type' => 'Style', 'id' => $pop->id, 'name' => 'Pop'],
                    ],
                    'location_name' => 'Lyon',
                    'note' => 'newest announce',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $author3->id,
                        'username' => 'author_three',
                        'has_musician_profile' => false,
                    ],
                ],
                [
                    '@id' => '/api/musician_announces/' . $middle->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $middle->id,
                    'creation_datetime' => '2022-02-02T02:02:02+00:00',
                    'type' => 1,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $drum->id,
                        'musician_name' => 'Batteur',
                    ],
                    'styles' => [],
                    'location_name' => 'Nantes',
                    'note' => 'middle announce',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $author2->id,
                        'username' => 'author_two',
                        'has_musician_profile' => false,
                    ],
                ],
                [
                    '@id' => '/api/musician_announces/' . $oldest->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $oldest->id,
                    'creation_datetime' => '2021-01-01T01:01:01+00:00',
                    'type' => 1,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $drum->id,
                        'musician_name' => 'Batteur',
                    ],
                    'styles' => [
                        ['@type' => 'Style', 'id' => $rock->id, 'name' => 'Rock'],
                    ],
                    'location_name' => 'Brest',
                    'note' => 'oldest announce',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $authorWithProfile->id,
                        'username' => 'author_musician',
                        'has_musician_profile' => true,
                    ],
                ],
            ],
            'totalItems' => 3,
        ]);

        // The endpoint must stay flat regardless of the number of distinct authors:
        // the announce list, the batched styles, and the projected authors = 3 queries.
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to assert the query count.');
        $this->assertLessThanOrEqual(3, $profile->getCollector('db')->getQueryCount());
    }
}
