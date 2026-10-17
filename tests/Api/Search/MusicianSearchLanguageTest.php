<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The language filter of the musician search (#1198): none by default, so nothing disappears, and once a
 * language is asked for, only the announces that welcome it.
 */
#[ResetDatabase]
class MusicianSearchLanguageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_without_a_language_every_announce_is_found(): void
    {
        $viewer = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $french = $this->announce('francais', ['fr'], '2026-01-04', $guitar, $rock);
        $bilingual = $this->announce('bilingue', ['fr', 'en'], '2026-01-03', $guitar, $rock);
        $english = $this->announce('english', ['en'], '2026-01-02', $guitar, $rock);
        // Cannot be created through the API, which always records one: only a filter could lose it.
        $none = $this->announce('sans_langue', [], '2026-01-01', $guitar, $rock);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/musicians/search');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 12,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/' . $french->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $french->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de francais',
                    'user' => ['@type' => 'User', 'id' => (string) $french->author->id, 'username' => 'francais', 'display_name' => 'francais', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => ['fr'],
                ],
                [
                    '@id' => '/api/announce_musicians/' . $bilingual->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $bilingual->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de bilingue',
                    'user' => ['@type' => 'User', 'id' => (string) $bilingual->author->id, 'username' => 'bilingue', 'display_name' => 'bilingue', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => ['fr', 'en'],
                ],
                [
                    '@id' => '/api/announce_musicians/' . $english->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $english->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de english',
                    'user' => ['@type' => 'User', 'id' => (string) $english->author->id, 'username' => 'english', 'display_name' => 'english', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => ['en'],
                ],
                [
                    '@id' => '/api/announce_musicians/' . $none->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $none->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de sans_langue',
                    'user' => ['@type' => 'User', 'id' => (string) $none->author->id, 'username' => 'sans_langue', 'display_name' => 'sans_langue', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => [],
                ],
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

    public function test_a_language_keeps_only_the_announces_that_welcome_it(): void
    {
        $viewer = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $this->announce('francais', ['fr'], '2026-01-04', $guitar, $rock);
        $bilingual = $this->announce('bilingue', ['fr', 'en'], '2026-01-03', $guitar, $rock);
        $english = $this->announce('english', ['en'], '2026-01-02', $guitar, $rock);
        $this->announce('sans_langue', [], '2026-01-01', $guitar, $rock);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/musicians/search', ['instrument' => (string) $guitar->id, 'language' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 12,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/' . $bilingual->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $bilingual->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de bilingue',
                    'user' => ['@type' => 'User', 'id' => (string) $bilingual->author->id, 'username' => 'bilingue', 'display_name' => 'bilingue', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => ['fr', 'en'],
                ],
                [
                    '@id' => '/api/announce_musicians/' . $english->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $english->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de english',
                    'user' => ['@type' => 'User', 'id' => (string) $english->author->id, 'username' => 'english', 'display_name' => 'english', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => ['en'],
                ],
            ],
            'view' => ['@id' => '/api/musicians/search?instrument=' . $guitar->id . '&language=en', '@type' => 'PartialCollectionView'],
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

    public function test_a_language_and_styles_must_both_match(): void
    {
        $viewer = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $this->announce('rock_fr', ['fr'], '2026-01-03', $guitar, $rock);
        $rockEnglish = $this->announce('rock_en', ['en', 'nl'], '2026-01-02', $guitar, $rock);
        $this->announce('pop_en', ['en'], '2026-01-01', $guitar, $pop);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/musicians/search', ['styles' => [(string) $rock->id], 'language' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 12,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/' . $rockEnglish->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $rockEnglish->id,
                    'location_name' => 'Bruxelles',
                    'note' => 'Annonce de rock_en',
                    'user' => ['@type' => 'User', 'id' => (string) $rockEnglish->author->id, 'username' => 'rock_en', 'display_name' => 'rock_en', 'has_musician_profile' => false],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Guitariste'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                    'languages' => ['en', 'nl'],
                ],
            ],
            'view' => ['@id' => '/api/musicians/search?language=en&styles%5B%5D=' . $rock->id, '@type' => 'PartialCollectionView'],
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

    public function test_a_language_nobody_welcomes_finds_nothing(): void
    {
        $viewer = UserFactory::new()->asBaseUser()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $this->announce('francais', ['fr'], '2026-01-02', $guitar, $rock);
        $this->announce('sans_langue', [], '2026-01-01', $guitar, $rock);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/musicians/search', ['language' => 'nl']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 12,
            'member' => [],
            'view' => ['@id' => '/api/musicians/search?language=nl', '@type' => 'PartialCollectionView'],
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

    /** @return iterable<string, array{string}> */
    public static function invalidLanguages(): iterable
    {
        yield 'a language the announces cannot have' => ['language=es'];
        yield 'an upper case code' => ['language=EN'];
        yield 'an empty language' => ['language='];
        yield 'a list of languages' => ['language[]=en'];
        yield 'a null byte' => ['language=en%00'];
    }

    #[DataProvider('invalidLanguages')]
    public function test_an_invalid_language_is_refused(string $query): void
    {
        $this->client->request('GET', '/api/musicians/search?' . $query);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'language', 'message' => "La langue n'est pas valide", 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "language: La langue n'est pas valide",
            'description' => "language: La langue n'est pas valide",
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /** @param list<string> $languages */
    private function announce(string $username, array $languages, string $day, Instrument $instrument, Style $style): MusicianAnnounce
    {
        return MusicianAnnounceFactory::new()
            ->withInstrument($instrument)
            ->withStyles([$style])
            ->asMusician()
            ->create([
                'author' => UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@example.com']),
                'languages' => $languages,
                'locationName' => 'Bruxelles',
                'note' => 'Annonce de ' . $username,
                'creationDatetime' => new \DateTime($day . 'T10:00:00+00:00'),
            ]);
    }
}
