<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Entity\Musician\MusicianAnnounce;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The note comes back as the plain text that was stored (#1139), like every other announce endpoint:
 * the clients escape it, and an HTML-escaped note showed up as « j&#039;ai » in the app.
 */
#[ResetDatabase]
class MusicianSearchNoteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string NOTE = "Débutant, mais j'ai quelques notions & de l'envie <3\nDispo le week-end";

    /** @return iterable<string, array{bool}> */
    public static function searches(): iterable
    {
        // Unfiltered is what the app's « Dernières annonces » asks for.
        yield 'unfiltered' => [false];
        yield 'filtered by instrument' => [true];
    }

    #[DataProvider('searches')]
    public function test_the_note_comes_back_exactly_as_stored(bool $byInstrument): void
    {
        $saxophone = InstrumentFactory::new()->create(['musicianName' => 'Saxophoniste', 'slug' => 'saxophone', 'name' => 'Saxophone']);
        $query = $byInstrument ? ['type' => '2', 'instrument' => (string) $saxophone->id] : ['type' => '2'];
        $viewId = $byInstrument ? '/api/musicians/search?instrument=' . $saxophone->id . '&type=2' : '/api/musicians/search?type=2';
        $jazz = StyleFactory::new()->asJazz()->create();
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'sax_debutant', 'email' => 'sax@test.com']);
        $announce = MusicianAnnounceFactory::new()->withInstrument($saxophone)->withStyles([$jazz])->create([
            'type' => MusicianAnnounce::TYPE_BAND,
            'author' => $author,
            'locationName' => 'Bruxelles',
            'note' => self::NOTE,
        ]);

        $this->client->request('GET', '/api/musicians/search', $query);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [[
                '@id' => '/api/announce_musicians/' . $announce->id,
                '@type' => 'AnnounceMusician',
                'id' => (string) $announce->id,
                'location_name' => 'Bruxelles',
                'note' => self::NOTE,
                'user' => ['@type' => 'User', 'id' => (string) $author->id, 'username' => 'sax_debutant', 'display_name' => 'sax_debutant', 'has_musician_profile' => false],
                'instrument' => ['@type' => 'Instrument', 'name' => 'Saxophoniste'],
                'type' => MusicianAnnounce::TYPE_BAND,
                'styles' => [['@type' => 'Style', 'name' => 'Jazz']],
            ]],
            'view' => ['@id' => $viewId, '@type' => 'PartialCollectionView'],
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
}
