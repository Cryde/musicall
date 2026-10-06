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
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
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
                        'display_name' => 'base_user_1',
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
                        'display_name' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
            ],
            'totalItems' => 2,
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musician_announces/last{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
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
        // The projection reads the profile too (#1118): a public name is shown, a private one is not.
        $author3->profile->displayName = 'Alexandre Martin';
        $author2->profile->displayName = 'Samuel Dupont';
        $author2->profile->isPublic = false;
        self::getContainer()->get('doctrine')->getManager()->flush();

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
        // Not paired with an EntityManager::clear() the way the file listing budget is: this endpoint
        // projects its authors rather than hydrating them, so there is nothing lazy for a warm identity
        // map to hide, and clearing reorders the styles collection, which carries no ORDER BY.
        // The debug holder lives as long as the connection, so the factory writes above are already in
        // it. Emptied here, getQueryCount() answers for the request alone rather than for the test.
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/musician_announces/last');

        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
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
                        'display_name' => 'Alexandre Martin',
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
                        'display_name' => 'author_two',
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
                        'display_name' => 'author_musician',
                        'has_musician_profile' => true,
                    ],
                ],
            ],
            'totalItems' => 3,
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musician_announces/last{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);

        // The endpoint must stay flat regardless of the number of distinct authors:
        // the announce list, the batched styles, and the projected authors = 3 queries.
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to assert the query count.');
        $this->assertLessThanOrEqual(3, $profile->getCollector('db')->getQueryCount());
    }

    public function test_last_announces_can_be_narrowed_to_one_type(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $drum = InstrumentFactory::new()->asDrum()->create();
        $bandAnnounce = MusicianAnnounceFactory::new()->create([
            'author' => $author,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'instrument' => $drum,
            'locationName' => 'Mons',
            'note' => 'a band looking for a drummer',
            'type' => 1,
            'styles' => [],
        ]);
        MusicianAnnounceFactory::new()->create([
            'author' => $author,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'instrument' => $drum,
            'locationName' => 'Paris',
            'note' => 'a drummer looking for a band',
            'type' => 2,
            'styles' => [],
        ]);

        $this->client->request('GET', '/api/musician_announces/last?type=1');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/last',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/musician_announces/' . $bandAnnounce->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $bandAnnounce->id,
                    'creation_datetime' => '2020-01-02T02:03:04+00:00',
                    'type' => 1,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $drum->id,
                        'musician_name' => 'Batteur',
                    ],
                    'styles' => [],
                    'location_name' => 'Mons',
                    'note' => 'a band looking for a drummer',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $author->id,
                        'username' => 'base_user_1',
                        'display_name' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
            ],
            'totalItems' => 1,
            'view' => [
                '@id' => '/api/musician_announces/last?type=1',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musician_announces/last{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_last_announces_can_be_narrowed_to_the_musicians(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $drum = InstrumentFactory::new()->asDrum()->create();
        MusicianAnnounceFactory::new()->create([
            'author' => $author,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'instrument' => $drum,
            'locationName' => 'Mons',
            'note' => 'a band looking for a drummer',
            'type' => 1,
            'styles' => [],
        ]);
        $musicianAnnounce = MusicianAnnounceFactory::new()->create([
            'author' => $author,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'instrument' => $drum,
            'locationName' => 'Paris',
            'note' => 'a drummer looking for a band',
            'type' => 2,
            'styles' => [],
        ]);

        $this->client->request('GET', '/api/musician_announces/last?type=2');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/last',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/musician_announces/' . $musicianAnnounce->id,
                    '@type' => 'MusicianAnnounce',
                    'id' => $musicianAnnounce->id,
                    'creation_datetime' => '2022-01-02T02:03:04+00:00',
                    'type' => 2,
                    'instrument' => [
                        '@type' => 'Instrument',
                        'id' => $drum->id,
                        'musician_name' => 'Batteur',
                    ],
                    'styles' => [],
                    'location_name' => 'Paris',
                    'note' => 'a drummer looking for a band',
                    'author' => [
                        '@type' => 'Author',
                        'id' => $author->id,
                        'username' => 'base_user_1',
                        'display_name' => 'base_user_1',
                        'has_musician_profile' => false,
                    ],
                ],
            ],
            'totalItems' => 1,
            'view' => [
                '@id' => '/api/musician_announces/last?type=2',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musician_announces/last{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->client->request('GET', '/api/musician_announces/last?type=3');

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => 'Le type d\'annonce est invalide',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'type: Le type d\'annonce est invalide',
            'description' => 'type: Le type d\'annonce est invalide',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
        ]);
    }
}
