<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MusicianAnnounceGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_item_exposes_every_language_as_stored(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'band_brussels', 'email' => 'band_brussels@email.com']);
        $style = StyleFactory::new()->asRock()->create();
        $instrument = InstrumentFactory::new()->asDrum()->create();
        $announce = MusicianAnnounceFactory::new()->create([
            'author' => $author,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-10-01T10:00:00+00:00'),
            'instrument' => $instrument,
            'locationName' => 'Bruxelles',
            'note' => 'Batteur francophone ou anglophone',
            'type' => 1,
            'styles' => [$style],
            'languages' => ['fr', 'en'],
        ]);

        $this->client->request('GET', '/api/musician_announces/' . $announce->id);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/' . $announce->id,
            '@type' => 'MusicianAnnounce',
            'id' => $announce->id,
            'creation_datetime' => '2026-10-01T10:00:00+00:00',
            'type' => 1,
            'instrument' => [
                '@type' => 'Instrument',
                'id' => $instrument->id,
                'musician_name' => 'Batteur',
            ],
            'styles' => [
                [
                    '@type' => 'Style',
                    'id' => $style->id,
                    'name' => 'Rock',
                ],
            ],
            'location_name' => 'Bruxelles',
            'note' => 'Batteur francophone ou anglophone',
            'languages' => ['fr', 'en'],
            'author' => [
                '@type' => 'Author',
                'id' => $author->id,
                'username' => 'band_brussels',
                'display_name' => 'band_brussels',
                'has_musician_profile' => false,
            ],
        ]);
    }

    public function test_get_item_unknown_announce_is_not_found(): void
    {
        $this->client->request('GET', '/api/musician_announces/00000000-0000-4000-8000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Musician announce not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Musician announce not found',
        ]);
    }

    public function test_get_item_with_an_id_that_is_not_a_uuid_is_not_found(): void
    {
        // A guid column converts nothing, so a malformed id is not found rather than a 500.
        $this->client->request('GET', '/api/musician_announces/not-a-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Musician announce not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Musician announce not found',
        ]);
    }
}
