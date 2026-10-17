<?php

namespace App\Tests\Api\Musician;

use App\Repository\Musician\MusicianAnnounceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MusicianAnnouncePostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_post(): void
    {
        $musicianAnnounceRepository = self::getContainer()->get(MusicianAnnounceRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();

        $style1 = StyleFactory::new()->asRock()->create();
        $style2 = StyleFactory::new()->asPop()->create();
        $style3 = StyleFactory::new()->asMetal()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();

        $result = $musicianAnnounceRepository->findBy(['author' => $user1]);
        $this->assertCount(0, $result);

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/musician_announces', [
            "type"          => 1,
            "instrument"    => "/api/instruments/" . $instrument1->id,
            "styles"        => [
                "/api/styles/" . $style1->id,
                "/api/styles/" . $style2->id,
                "/api/styles/" . $style3->id,
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
            '@id' => '/api/musician_announces/' . $createdAnnounce->id,
            '@type' => 'MusicianAnnounce',
            'id' => $createdAnnounce->id,
            'creation_datetime' => $createdAnnounce->creationDatetime->format('c'),
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
                [
                    '@type' => 'Style',
                    'id' => $style2->id,
                    'name' => 'Pop',
                ],
                [
                    '@type' => 'Style',
                    'id' => $style3->id,
                    'name' => 'Metal',
                ],
            ],
            'location_name' => 'Brussels',
            'note' => 'This is a note for the announce',
            'languages' => ['fr'],
            'author' => [
                '@type' => 'Author',
                'id' => $user1->id,
                'username' => $user1->username,
                'display_name' => $user1->username,
                'has_musician_profile' => false,
            ],
        ]);
    }

    /** @return iterable<string, array{array<string, null>, string, list<string>}> */
    public static function languagesLeftToTheServer(): iterable
    {
        // What a client that does not ask for the languages sends (the mobile app).
        yield 'no languages sent' => [[], 'en', ['en']];
        yield 'languages sent as null' => [['languages' => null], 'en', ['en']];
        yield 'an account language an announce cannot have' => [[], 'es', ['fr']];
    }

    /**
     * @param array<string, null> $languages
     * @param list<string> $expected
     */
    #[DataProvider('languagesLeftToTheServer')]
    public function test_post_without_languages_takes_the_author_locale(array $languages, string $authorLocale, array $expected): void
    {
        $musicianAnnounceRepository = self::getContainer()->get(MusicianAnnounceRepository::class);
        $author = UserFactory::new()->asBaseUser()->create(['locale' => $authorLocale]);
        $style = StyleFactory::new()->asRock()->create();
        $instrument = InstrumentFactory::new()->asDrum()->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/musician_announces', [
            'type'          => 1,
            'instrument'    => '/api/instruments/' . $instrument->id,
            'styles'        => ['/api/styles/' . $style->id],
            'location_name' => 'Brussels',
            'longitude'     => '4.3517103',
            'latitude'      => '50.8503396',
        ] + $languages, ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseIsSuccessful();

        $result = $musicianAnnounceRepository->findBy(['author' => $author]);
        $this->assertCount(1, $result);
        $createdAnnounce = $result[0];
        $this->assertSame($expected, $createdAnnounce->languages);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/' . $createdAnnounce->id,
            '@type' => 'MusicianAnnounce',
            'id' => $createdAnnounce->id,
            'creation_datetime' => $createdAnnounce->creationDatetime->format('c'),
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
            'location_name' => 'Brussels',
            'languages' => $expected,
            'author' => [
                '@type' => 'Author',
                'id' => $author->id,
                'username' => $author->username,
                'display_name' => $author->username,
                'has_musician_profile' => false,
            ],
        ]);
    }

    public function test_post_with_languages_keeps_them(): void
    {
        $musicianAnnounceRepository = self::getContainer()->get(MusicianAnnounceRepository::class);
        $author = UserFactory::new()->asBaseUser()->create(['locale' => 'en']);
        $style = StyleFactory::new()->asRock()->create();
        $instrument = InstrumentFactory::new()->asDrum()->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/musician_announces', [
            'type'          => 1,
            'instrument'    => '/api/instruments/' . $instrument->id,
            'styles'        => ['/api/styles/' . $style->id],
            'location_name' => 'Brussels',
            'longitude'     => '4.3517103',
            'latitude'      => '50.8503396',
            'languages'     => ['nl', 'fr'],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseIsSuccessful();

        $result = $musicianAnnounceRepository->findBy(['author' => $author]);
        $this->assertCount(1, $result);
        $createdAnnounce = $result[0];
        $this->assertSame(['nl', 'fr'], $createdAnnounce->languages);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/' . $createdAnnounce->id,
            '@type' => 'MusicianAnnounce',
            'id' => $createdAnnounce->id,
            'creation_datetime' => $createdAnnounce->creationDatetime->format('c'),
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
            'location_name' => 'Brussels',
            'languages' => ['nl', 'fr'],
            'author' => [
                '@type' => 'Author',
                'id' => $author->id,
                'username' => $author->username,
                'display_name' => $author->username,
                'has_musician_profile' => false,
            ],
        ]);
    }

    /** @return iterable<string, array{mixed, string, string, string}> */
    public static function invalidLanguages(): iterable
    {
        yield 'no language' => [[], 'languages', 'Veuillez choisir au moins une langue', Count::TOO_FEW_ERROR];
        yield 'a language the form does not offer' => [['fr', 'es'], 'languages[1]', "Cette langue n'est pas proposée", Choice::NO_SUCH_CHOICE_ERROR];
        yield 'an upper case code' => [['FR'], 'languages[0]', "Cette langue n'est pas proposée", Choice::NO_SUCH_CHOICE_ERROR];
        yield 'a null language' => [[null], 'languages[0]', "Cette langue n'est pas proposée", NotNull::IS_NULL_ERROR];
        yield 'a keyed object' => [['a' => 'fr'], 'languages', "Cette langue n'est pas proposée", Type::INVALID_TYPE_ERROR];
        yield 'an object with numeric keys' => [[1 => 'fr', 2 => 'en'], 'languages', "Cette langue n'est pas proposée", Type::INVALID_TYPE_ERROR];
        yield 'a language not in a list' => ['fr', 'languages', 'Cette valeur doit être de type list<string>|null.', Type::INVALID_TYPE_ERROR];
        yield 'the same language twice' => [['fr', 'fr'], 'languages', "Chaque langue ne peut être choisie qu'une fois", Unique::IS_NOT_UNIQUE];
    }

    #[DataProvider('invalidLanguages')]
    public function test_post_with_invalid_languages_is_refused(mixed $languages, string $propertyPath, string $message, string $code): void
    {
        $musicianAnnounceRepository = self::getContainer()->get(MusicianAnnounceRepository::class);
        $author = UserFactory::new()->asBaseUser()->create();
        $style = StyleFactory::new()->asRock()->create();
        $instrument = InstrumentFactory::new()->asDrum()->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/musician_announces', [
            'type'          => 1,
            'instrument'    => '/api/instruments/' . $instrument->id,
            'styles'        => ['/api/styles/' . $style->id],
            'location_name' => 'Brussels',
            'longitude'     => '4.3517103',
            'latitude'      => '50.8503396',
            'languages'     => $languages,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . $code,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => $propertyPath, 'message' => $message, 'code' => $code],
            ],
            'detail' => $propertyPath . ': ' . $message,
            'description' => $propertyPath . ': ' . $message,
            'type' => '/validation_errors/' . $code,
            'title' => 'An error occurred',
        ]);
        $this->assertCount(0, $musicianAnnounceRepository->findBy(['author' => $author]));
    }

    public function test_post_not_logged_in(): void
    {
        $this->client->request('POST', '/api/musician_announces', [], [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
