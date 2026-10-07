<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * An instrument or a style that is not an id is the caller's mistake, never a 500 (#1141). Both the
 * search and its widening read the two parameters the same way.
 */
#[ResetDatabase]
class MusicianSearchInvalidParameterTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string UNKNOWN_ID = '00000000-0000-4000-8000-000000000000';

    /** @return iterable<string, array{string, string, string, string}> */
    public static function malformedParameters(): iterable
    {
        foreach (['/api/musicians/search', '/api/musicians/search/widen'] as $endpoint) {
            yield $endpoint . ', an instrument that is not an id' => [$endpoint, 'instrument=abc', 'instrument', "L'instrument n'est pas valide"];
            yield $endpoint . ', an instrument with a null byte' => [$endpoint, 'instrument=abc%00', 'instrument', "L'instrument n'est pas valide"];
            yield $endpoint . ', an instrument given as a list' => [$endpoint, 'instrument[]=abc', 'instrument', "L'instrument n'est pas valide"];
            yield $endpoint . ', a style that is not an id' => [$endpoint, 'styles[]=abc', 'styles', "Le style n'est pas valide"];
            yield $endpoint . ', a style given alone' => [$endpoint, 'styles=abc', 'styles', "Le style n'est pas valide"];
            yield $endpoint . ', an empty instrument' => [$endpoint, 'instrument=', 'instrument', "L'instrument n'est pas valide"];
            // Well-formed ids in a shape the filter cannot take: one instrument, a plain list of styles.
            yield $endpoint . ', several instruments' => [$endpoint, 'instrument[]=' . self::UNKNOWN_ID, 'instrument', "L'instrument n'est pas valide"];
            yield $endpoint . ', a keyed instrument' => [$endpoint, 'instrument[a]=' . self::UNKNOWN_ID, 'instrument', "L'instrument n'est pas valide"];
            yield $endpoint . ', keyed styles' => [$endpoint, 'styles[a]=' . self::UNKNOWN_ID, 'styles', "Le style n'est pas valide"];
        }
    }

    #[DataProvider('malformedParameters')]
    public function test_a_malformed_id_is_refused(string $endpoint, string $query, string $parameter, string $message): void
    {
        $this->client->request('GET', $endpoint . '?type=2&' . $query);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => $parameter, 'message' => $message, 'code' => Uuid::INVALID_CHARACTERS_ERROR],
            ],
            'detail' => $parameter . ': ' . $message,
            'description' => $parameter . ': ' . $message,
            'type' => '/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /** @return iterable<string, array{string}> */
    public static function endpoints(): iterable
    {
        yield 'search' => ['/api/musicians/search'];
        yield 'widening' => ['/api/musicians/search/widen'];
    }

    #[DataProvider('endpoints')]
    public function test_an_unknown_instrument_is_not_found(string $endpoint): void
    {
        $this->client->request('GET', $endpoint . '?type=2&instrument=' . self::UNKNOWN_ID);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Instrument introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Instrument introuvable',
        ]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function acceptedShapes(): iterable
    {
        foreach (self::endpoints() as $name => [$endpoint]) {
            yield $name . ', a known instrument' => [$endpoint, 'instrument={drum}'];
            yield $name . ', a style given once' => [$endpoint, 'styles={rock}'];
            yield $name . ', a list of styles' => [$endpoint, 'styles[]={rock}'];
            // Skipped by the lookup rather than refused: the search simply finds nothing for it.
            yield $name . ', an unknown style in a list' => [$endpoint, 'styles[]=' . self::UNKNOWN_ID];
        }
    }

    #[DataProvider('acceptedShapes')]
    public function test_a_well_formed_filter_is_accepted(string $endpoint, string $query): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $this->client->request('GET', $endpoint . '?type=2&' . strtr($query, ['{drum}' => (string) $drum->id, '{rock}' => (string) $rock->id]));

        $this->assertResponseIsSuccessful();
    }
}
