<?php

namespace App\Tests\Api\Announce;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AnnounceSearchTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_search_musicians_with_required_parameters(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $author1 = UserFactory::new()->asBaseUser()->create(['username' => 'philip', 'email' => 'email']);
        $author2 = UserFactory::new()->asBaseUser()->create(['username' => 'olivia', 'email' => 'olivia@mail.com']);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
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
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/'.$announce2->getId(),
                    '@type' => 'AnnounceMusician',
                    'id' => $announce2->getId(),
                    'location_name' => 'Lyon',
                    'note' => 'Guitariste rock cherche groupe 2',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author2->getId(),
                        'username' => 'olivia',
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Guitariste'
                    ],
                    'type' => 1,
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'name'  => 'Rock',
                        ],
                    ],
                ],
                [
                    '@id' => '/api/announce_musicians/'.$announce1->getId(),
                    '@type' => 'AnnounceMusician',
                    'id' => $announce1->getId(),
                    'location_name' => 'Paris',
                    'note' => 'Guitariste rock cherche groupe',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author1->getId(),
                        'username' => 'philip',
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Guitariste'
                    ],
                    'type' => 1,
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'name' => 'Rock'
                        ],
                        [
                            '@type' => 'Style',
                            'name' => 'Pop'
                        ]
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/musicians/search?instrument=' . $guitar->getId() . '&type=1',
                '@type' => 'PartialCollectionView'
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musicians/search{?type,instrument,styles}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => true,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'instrument',
                        'property' => 'instrument',
                        'required' => true
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'styles',
                        'property' => 'styles',
                        'required' => false
                    ],
                ]
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

        $announce1 = MusicianAnnounceFactory::new()
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

        $announce2 = MusicianAnnounceFactory::new()
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
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/'.$announce1->getId(),
                    '@type' => 'AnnounceMusician',
                    'id' => $announce1->getId(),
                    'location_name' => 'Paris',
                    'note' => 'Batteur metal à Paris',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author1->getId(),
                        'username' => 'drummer1',
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Batteur'
                    ],
                    'type' => 1,
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'name' => 'Metal'
                        ],
                    ],
                    'distance' => 0.0
                ],
                [
                    '@id' => '/api/announce_musicians/'.$announce2->getId(),
                    '@type' => 'AnnounceMusician',
                    'id' => $announce2->getId(),
                    'location_name' => 'Marseille',
                    'note' => 'Batteur metal à Marseille',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author2->getId(),
                        'username' => 'drummer2',
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Batteur'
                    ],
                    'type' => 1,
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'name' => 'Metal'
                        ],
                    ],
                    'distance' => 660.476928300675
                ],
            ],
            'view' => [
                '@id' => '/api/musicians/search?instrument=' . $drum->getId() . '&latitude=48.8566&longitude=2.3522&type=1',
                '@type' => 'PartialCollectionView'
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musicians/search{?type,instrument,styles}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => true,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'instrument',
                        'property' => 'instrument',
                        'required' => true
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'styles',
                        'property' => 'styles',
                        'required' => false
                    ],
                ]
            ],
        ]);
    }

    public function test_search_musicians_with_styles_filter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $author2 = UserFactory::new()->asBaseUser()->create(['username' => 'olivia', 'email' => 'olivia@email.com']);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock, $pop])
            ->asMusician()
            ->create(['author' => $author2, 'note' => 'some notes']);

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

        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/'.$announce1->getId(),
                    '@type' => 'AnnounceMusician',
                    'id' => $announce1->getId(),
                    'location_name' => $announce1->getLocationName(),
                    'note' => 'some notes',
                    'user' => [
                        '@type' => 'User',
                        'id' => $author2->getId(),
                        'username' => 'olivia',
                    ],
                    'instrument' => [
                        '@type' => 'Instrument',
                        'name' => 'Guitariste'
                    ],
                    'type' => 1,
                    'styles' => [
                        [
                            '@type' => 'Style',
                            'name' => 'Rock'
                        ],
                        [
                            '@type' => 'Style',
                            'name' => 'Pop'
                        ]
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/musicians/search?instrument=' . $guitar->getId() . '&styles%5B%5D='.$rock->getId().'&type=1',
                '@type' => 'PartialCollectionView'
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musicians/search{?type,instrument,styles}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => true,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'instrument',
                        'property' => 'instrument',
                        'required' => true
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'styles',
                        'property' => 'styles',
                        'required' => false
                    ],
                ]
            ],
        ]);
    }

    public function test_search_musicians_without_required_type_parameter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/musicians/search',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=ad32d13f-c3d4-423b-909a-857b961eb720;1=ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => 'Cette valeur ne doit pas être nulle.',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
                [
                    'propertyPath' => 'instrument',
                    'message' => 'Cette valeur ne doit pas être nulle.',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
            ],
            'detail' => 'type: Cette valeur ne doit pas être nulle.
instrument: Cette valeur ne doit pas être nulle.',
            'description' => 'type: Cette valeur ne doit pas être nulle.
instrument: Cette valeur ne doit pas être nulle.',
            'type' => '/validation_errors/0=ad32d13f-c3d4-423b-909a-857b961eb720;1=ad32d13f-c3d4-423b-909a-857b961eb720',
            'title' => 'An error occurred',
        ]);
    }
}
