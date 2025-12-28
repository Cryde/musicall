<?php

namespace App\Tests\Api\Musician;

use App\Repository\Musician\MusicianAnnounceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianAnnouncePostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_post(): void
    {
        $musicianAnnounceRepository = self::getContainer()->get(MusicianAnnounceRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();

        $style1 = StyleFactory::new()->asRock()->create();
        $style2 = StyleFactory::new()->asPop()->create();
        $style3 = StyleFactory::new()->asMetal()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();

        $user1 = $user1->_real();
        $instrument1 = $instrument1->_real();
        $style1 = $style1->_real();
        $style2 = $style2->_real();
        $style3 = $style3->_real();

        $result = $musicianAnnounceRepository->findBy(['author' => $user1]);
        $this->assertCount(0, $result);

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/musician_announces', [
            "type"          => 1,
            "instrument"    => "/api/instruments/" . $instrument1->getId(),
            "styles"        => [
                "/api/styles/" . $style1->getId(),
                "/api/styles/" . $style2->getId(),
                "/api/styles/" . $style3->getId(),
            ],
            "location_name" => "Brussels",
            "longitude"     => "4.3517103",
            "latitude"      => "50.8503396",
            "note"          => "This is a note for the announce",
        ] , ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();

        $result = $musicianAnnounceRepository->findBy(['author' => $user1]);
        $this->assertCount(1, $result);

        $createdAnnounce = $result[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/' . $createdAnnounce->getId(),
            '@type' => 'MusicianAnnounce',
            'id' => $createdAnnounce->getId(),
            'creation_datetime' => $createdAnnounce->getCreationDatetime()->format('c'),
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
                [
                    '@type' => 'Style',
                    'id' => $style2->getId(),
                    'name' => 'Pop',
                ],
                [
                    '@type' => 'Style',
                    'id' => $style3->getId(),
                    'name' => 'Metal',
                ],
            ],
            'location_name' => 'Brussels',
            'note' => 'This is a note for the announce',
            'author' => [
                '@type' => 'Author',
                'id' => $user1->getId(),
                'username' => $user1->getUsername(),
            ],
        ]);
    }

    public function test_post_not_logged_in(): void
    {
        $this->client->request('POST', '/api/musician_announces', [], [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
