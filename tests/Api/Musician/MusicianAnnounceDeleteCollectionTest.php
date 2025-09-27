<?php

namespace App\Tests\Api\Musician;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianAnnounceDeleteCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_musician_announces(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $style1 = StyleFactory::new()->asRock()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();
        $user1Announce1 = MusicianAnnounceFactory::new()->create([
            'author' => $user1,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'instrument' => $instrument1,
            'locationName' => 'Mons',
            'note' => 'note announce 1',
            'type' => 1, // type musician
            'styles' => [$style1]
        ]);

        $user1 = $user1->_real();

        $this->client->loginUser($user1);
        $this->client->request('DELETE', '/api/user/musician/announces/' . $user1Announce1->getId());
        $this->assertResponseIsSuccessful();
    }

    public function test_delete_musician_announces_not_owned(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $style1 = StyleFactory::new()->asRock()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();

        $user1Announce1 = MusicianAnnounceFactory::new()->create([
            'author' => $user1,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'instrument' => $instrument1,
            'locationName' => 'Mons',
            'note' => 'note announce 1',
            'type' => 1, // type musician
            'styles' => [$style1]
        ]);

        $user2 = $user2->_real();

        $this->client->loginUser($user2);
        $this->client->request('DELETE', '/api/user/musician/announces/' . $user1Announce1->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Announce not found.',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Announce not found.',
        ]);
    }

    public function test_delete_musician_announces_not_logged_in(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $announce = MusicianAnnounceFactory::new()->create(['author' => $user1,]);

        $this->client->request('DELETE', '/api/user/musician/announces/' . $announce->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
