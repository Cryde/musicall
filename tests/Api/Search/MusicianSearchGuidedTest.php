<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * What the guided search needs from the musician search (#1084): a radius, one place per announce
 * whatever its number of matching styles, and which wider search would find something.
 */
#[ResetDatabase]
class MusicianSearchGuidedTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array BRUSSELS = ['latitude' => '50.8503', 'longitude' => '4.3517'];
    private const array LIEGE = ['latitude' => '50.6326', 'longitude' => '5.5797'];

    public function test_a_radius_keeps_the_announces_within_it(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $near = $this->announce('proche', $drum, [$rock], 'Bruxelles', self::BRUSSELS);
        // About 90 km away.
        $this->announce('loin', $drum, [$rock], 'Liège', self::LIEGE);

        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/musicians/search', ['type' => '2', 'radius' => '25'] + self::BRUSSELS);
        $this->assertNoQueryReadsTable('user_profile', 'An author profile must come with the results, never in a query of its own');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [$this->member($near, 'proche', 'Bruxelles', [['@type' => 'Style', 'name' => 'Rock']], 0.0)],
            'view' => [
                '@id' => '/api/musicians/search?latitude=50.8503&longitude=4.3517&radius=25&type=2',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->searchTemplate(),
        ]);
    }

    public function test_an_announce_with_several_of_the_styles_takes_one_place(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $announce = $this->announce('rockpop', $drum, [$rock, $pop], 'Bruxelles', self::BRUSSELS);

        $this->client->request('GET', '/api/musicians/search', ['type' => '2', 'styles' => [$rock->id, $pop->id]]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [$this->member($announce, 'rockpop', 'Bruxelles', [
                ['@type' => 'Style', 'name' => 'Rock'],
                ['@type' => 'Style', 'name' => 'Pop'],
            ])],
            'view' => [
                '@id' => '/api/musicians/search?styles%5B%5D=' . $rock->id . '&styles%5B%5D=' . $pop->id . '&type=2',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->searchTemplate(),
        ]);
    }

    public function test_a_radius_out_of_range_is_refused(): void
    {
        $this->client->request('GET', '/api/musicians/search', ['radius' => '0'] + self::BRUSSELS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'radius',
                    'message' => 'La distance doit être comprise entre 1 et 500 km',
                    'code' => Range::NOT_IN_RANGE_ERROR,
                ],
            ],
            'detail' => 'radius: La distance doit être comprise entre 1 et 500 km',
            'description' => 'radius: La distance doit être comprise entre 1 et 500 km',
            'type' => '/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_radius_that_is_not_a_whole_number_is_refused(): void
    {
        $this->client->request('GET', '/api/musicians/search', ['radius' => '25km'] + self::BRUSSELS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'radius',
                    'message' => 'La distance doit être un nombre entier de kilomètres',
                    'code' => Regex::REGEX_FAILED_ERROR,
                ],
            ],
            'detail' => 'radius: La distance doit être un nombre entier de kilomètres',
            'description' => 'radius: La distance doit être un nombre entier de kilomètres',
            'type' => '/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_the_wider_searches_that_would_find_something_are_offered(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        // Rock but 90 km away: found by a wider radius. Jazz but here: found by any style.
        $this->announce('loin', $drum, [$rock], 'Liège', self::LIEGE);
        $this->announce('jazz', $drum, [$jazz], 'Bruxelles', self::BRUSSELS);

        $this->client->request('GET', '/api/musicians/search/widen', [
            'type' => '2',
            'instrument' => $drum->id,
            'styles' => [$rock->id],
            'radius' => '25',
        ] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SearchWidening',
            '@id' => '/api/musicians/search/widen',
            '@type' => 'SearchWidening',
            'wider_radius' => true,
            'wider_radius_km' => 100,
            'all_styles' => true,
        ]);
    }

    public function test_a_wider_search_is_not_offered_when_it_would_find_nothing(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        // Another instrument: no widening of distance or styles finds a drummer.
        $this->announce('guitariste', $guitar, [$rock], 'Liège', self::LIEGE);

        $this->client->request('GET', '/api/musicians/search/widen', [
            'type' => '2',
            'instrument' => $drum->id,
            'styles' => [$rock->id],
            'radius' => '25',
        ] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SearchWidening',
            '@id' => '/api/musicians/search/widen',
            '@type' => 'SearchWidening',
            'wider_radius' => false,
            'wider_radius_km' => 100,
            'all_styles' => false,
        ]);
    }

    public function test_a_search_already_at_the_wider_radius_is_not_offered_it_again(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $this->announce('loin', $drum, [$rock], 'Liège', self::LIEGE);

        $this->client->request('GET', '/api/musicians/search/widen', [
            'type' => '2',
            'instrument' => $drum->id,
            'radius' => '100',
        ] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SearchWidening',
            '@id' => '/api/musicians/search/widen',
            '@type' => 'SearchWidening',
            'wider_radius' => false,
            'wider_radius_km' => 100,
            'all_styles' => false,
        ]);
    }

    /**
     * @param list<Style> $styles
     * @param array{latitude: string, longitude: string} $point
     */
    public function test_users_blocked_either_way_are_left_out(): void
    {
        // #1117: whoever placed the block, neither finds the other.
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $visible = $this->announce('visible', $drum, [$rock], 'Bruxelles', self::BRUSSELS);
        $blockedByViewer = $this->announce('bloque', $drum, [$rock], 'Bruxelles', self::BRUSSELS);
        $blockingViewer = $this->announce('bloqueur', $drum, [$rock], 'Bruxelles', self::BRUSSELS);
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'viewer', 'email' => 'viewer@test.com']);
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blockedByViewer->author]);
        UserBlockFactory::new()->create(['blocker' => $blockingViewer->author, 'blocked' => $viewer]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/musicians/search', ['type' => '2', 'radius' => '25'] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 12,
            'member' => [$this->member($visible, 'visible', 'Bruxelles', [['@type' => 'Style', 'name' => 'Rock']], 0.0)],
            'view' => [
                '@id' => '/api/musicians/search?latitude=50.8503&longitude=4.3517&radius=25&type=2',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->searchTemplate(),
        ]);
    }

    private function announce(string $username, Instrument $instrument, array $styles, string $city, array $point): MusicianAnnounce
    {
        return MusicianAnnounceFactory::new()
            ->withInstrument($instrument)
            ->withStyles($styles)
            ->create([
                'type' => MusicianAnnounce::TYPE_BAND,
                'author' => UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']),
                'locationName' => $city,
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'note' => 'Annonce de ' . $username,
                'creationDatetime' => new \DateTime('2026-09-01T10:00:00+00:00'),
            ]);
    }

    /**
     * @param list<array{'@type': string, name: string}> $styles
     *
     * @return array<string, mixed>
     */
    private function member(MusicianAnnounce $announce, string $username, string $city, array $styles, ?float $distance = null): array
    {
        /** @var User $author */
        $author = $announce->author;
        $member = [
            '@id' => '/api/announce_musicians/' . $announce->id,
            '@type' => 'AnnounceMusician',
            'id' => (string) $announce->id,
            'location_name' => $city,
            'note' => 'Annonce de ' . $username,
            'user' => ['@type' => 'User', 'id' => (string) $author->id, 'username' => $username, 'display_name' => $username, 'has_musician_profile' => false],
            'instrument' => ['@type' => 'Instrument', 'name' => 'Batteur'],
            'type' => MusicianAnnounce::TYPE_BAND,
            'styles' => $styles,
        ];
        if ($distance !== null) {
            $member['distance'] = $distance;
        }

        return $member;
    }

    /** @return array<string, mixed> */
    private function searchTemplate(): array
    {
        return [
            '@type' => 'IriTemplate',
            'template' => '/api/musicians/search{?type,instrument,styles}',
            'variableRepresentation' => 'BasicRepresentation',
            'mapping' => [
                ['@type' => 'IriTemplateMapping', 'variable' => 'type', 'property' => 'type', 'required' => false],
                ['@type' => 'IriTemplateMapping', 'variable' => 'instrument', 'property' => 'instrument', 'required' => false],
                ['@type' => 'IriTemplateMapping', 'variable' => 'styles', 'property' => 'styles', 'required' => false],
            ],
        ];
    }
}
