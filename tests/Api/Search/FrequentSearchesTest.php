<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\MusicianSearchKind;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** « Recherches fréquentes » (#1075): what enough different people searched for lately. */
#[ResetDatabase]
class FrequentSearchesTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_searches_enough_people_ran_are_listed_most_people_first(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        foreach (['a', 'b', 'c', 'd'] as $visitor) {
            $this->seed(2, $drum, 'Bruxelles', 50.84, 4.35, $visitor);
        }
        foreach (['a', 'b', 'c'] as $visitor) {
            $this->seed(1, $guitar, 'Paris', 48.85, 2.35, $visitor);
        }

        $this->client->request('GET', '/api/musicians/search/frequent');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FrequentSearches',
            '@id' => '/api/musicians/search/frequent',
            '@type' => 'FrequentSearches',
            'searches' => [
                [
                    '@type' => 'FrequentSearch',
                    'type' => 2,
                    'instrument_id' => (string) $drum->id,
                    'instrument_name' => 'Batteur',
                    'location_name' => 'Bruxelles',
                    'latitude' => 50.84,
                    'longitude' => 4.35,
                ],
                [
                    '@type' => 'FrequentSearch',
                    'type' => 1,
                    'instrument_id' => (string) $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'location_name' => 'Paris',
                    'latitude' => 48.85,
                    'longitude' => 2.35,
                ],
            ],
        ]);
    }

    public function test_one_person_repeating_a_search_does_not_make_it_frequent(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        foreach (range(1, 5) as $ignored) {
            $this->seed(2, $drum, 'Bruxelles', 50.84, 4.35, 'same');
        }
        $this->seed(2, $drum, 'Bruxelles', 50.84, 4.35, 'other');

        $this->client->request('GET', '/api/musicians/search/frequent');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FrequentSearches',
            '@id' => '/api/musicians/search/frequent',
            '@type' => 'FrequentSearches',
            'searches' => [],
        ]);
    }

    public function test_old_searches_ai_searches_and_searches_without_a_city_do_not_count(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        foreach (['a', 'b', 'c'] as $visitor) {
            $this->seed(2, $drum, 'Bruxelles', 50.84, 4.35, $visitor, new \DateTimeImmutable('-31 days'));
            $this->seed(2, $drum, 'Liège', 50.63, 5.57, $visitor, kind: MusicianSearchKind::Ai);
            $this->seed(2, $drum, null, null, null, $visitor);
        }

        $this->client->request('GET', '/api/musicians/search/frequent');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FrequentSearches',
            '@id' => '/api/musicians/search/frequent',
            '@type' => 'FrequentSearches',
            'searches' => [],
        ]);
    }

    private function seed(
        int $type,
        Instrument $instrument,
        ?string $locationName,
        ?float $latitude,
        ?float $longitude,
        string $visitor,
        \DateTimeImmutable $when = new \DateTimeImmutable('-1 day'),
        MusicianSearchKind $kind = MusicianSearchKind::Filters,
    ): void {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $log = new MusicianSearchLog($kind, hash('sha256', $visitor), $when);
        $log->type = $type;
        $log->instrument = $entityManager->getReference(Instrument::class, $instrument->id);
        $log->locationName = $locationName;
        $log->latitude = $latitude;
        $log->longitude = $longitude;
        $entityManager->persist($log);
        $entityManager->flush();
    }
}
