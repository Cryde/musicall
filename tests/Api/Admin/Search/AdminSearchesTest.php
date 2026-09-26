<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\AiSearchOutcome;
use App\Enum\Search\MusicianSearchKind;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Date;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The admin's view of the musician searches (#1075). */
#[ResetDatabase]
class AdminSearchesTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_overview_needs_a_login(): void
    {
        $this->client->request('GET', '/api/admin/searches/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_overview_is_refused_to_a_member(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/admin/searches/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
        ]);
    }

    public function test_the_overview_counts_the_period_and_ranks_its_combinations(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $this->seedFilters(2, $drum, 'Bruxelles', 'a', 3, '2026-09-10 10:00:00');
        $this->seedFilters(2, $drum, 'Bruxelles', 'a', 0, '2026-09-10 11:00:00');
        $this->seedFilters(2, $drum, 'Bruxelles', 'b', 0, '2026-09-11 10:00:00');
        $this->seedFilters(1, null, null, 'c', 5, '2026-09-12 10:00:00');
        // Outside the period.
        $this->seedFilters(2, $drum, 'Bruxelles', 'd', 0, '2026-08-31 23:00:00');
        $this->seedAi('Je cherche un batteur à Bruxelles', AiSearchOutcome::Filters, '2026-09-10 09:00:00');
        $this->seedAi('Bonjour', AiSearchOutcome::Nothing, '2026-09-30 22:00:00');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/searches/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminSearchOverview',
            '@id' => '/api/admin/searches/overview',
            '@type' => 'AdminSearchOverview',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'searches' => 4,
            'ai_searches' => 2,
            'zero_result_searches' => 2,
            'ai_filters' => 1,
            'ai_nothing' => 1,
            'ai_failed' => 0,
            'top_combinations' => [
                [
                    '@type' => 'AdminSearchCombination',
                    'type' => 2,
                    'instrument_name' => 'Batteur',
                    'location_name' => 'Bruxelles',
                    'searches' => 3,
                    'visitors' => 2,
                    'zero_results' => 2,
                ],
                [
                    '@type' => 'AdminSearchCombination',
                    'type' => 1,
                    'searches' => 1,
                    'visitors' => 1,
                    'zero_results' => 0,
                ],
            ],
        ]);
    }

    public function test_the_overview_refuses_a_malformed_date(): void
    {
        $this->client->loginUser(UserFactory::new()->asAdminUser()->create());
        $this->client->request('GET', '/api/admin/searches/overview?from=2026-02-31&to=2026-09-30');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_DATE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from',
                    'message' => 'Cette valeur n\'est pas une date valide.',
                    'code' => Date::INVALID_DATE_ERROR,
                ],
            ],
            'detail' => 'from: Cette valeur n\'est pas une date valide.',
            'description' => 'from: Cette valeur n\'est pas une date valide.',
            'type' => '/validation_errors/' . Date::INVALID_DATE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_the_ai_searches_are_listed_newest_first_with_what_they_produced(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $older = $this->seedAi('Bonjour', AiSearchOutcome::Nothing, '2026-09-10 09:00:00');
        $newer = $this->seedAi('Je cherche un batteur rock à Paris', AiSearchOutcome::Filters, '2026-09-11 09:00:00', 1, $drum, [(string) $rock->id], 48.85, 2.35);
        $this->seedFilters(2, $drum, 'Paris', 'a', 1, '2026-09-11 10:00:00');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/searches/ai');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminAiSearch',
            '@id' => '/api/admin/searches/ai',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/admin/searches/ai/' . $newer->id,
                    '@type' => 'AdminAiSearch',
                    'id' => (string) $newer->id,
                    'query' => 'Je cherche un batteur rock à Paris',
                    'outcome' => 'filters',
                    'type' => 1,
                    'instrument_name' => 'Batteur',
                    'style_names' => ['Rock'],
                    'latitude' => 48.85,
                    'longitude' => 2.35,
                    'authenticated' => false,
                    'search_datetime' => '2026-09-11T09:00:00+00:00',
                ],
                [
                    '@id' => '/api/admin/searches/ai/' . $older->id,
                    '@type' => 'AdminAiSearch',
                    'id' => (string) $older->id,
                    'query' => 'Bonjour',
                    'outcome' => 'nothing',
                    'style_names' => [],
                    'authenticated' => false,
                    'search_datetime' => '2026-09-10T09:00:00+00:00',
                ],
            ],
        ]);
    }

    public function test_the_ai_searches_are_refused_to_a_member(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/admin/searches/ai');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
        ]);
    }

    private function seedFilters(int $type, ?Instrument $instrument, ?string $city, string $visitor, int $results, string $when): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $log = new MusicianSearchLog(MusicianSearchKind::Filters, hash('sha256', $visitor), new \DateTimeImmutable($when));
        $log->type = $type;
        $log->instrument = $instrument instanceof Instrument ? $entityManager->getReference(Instrument::class, $instrument->id) : null;
        $log->locationName = $city;
        $log->firstPageResultCount = $results;
        $entityManager->persist($log);
        $entityManager->flush();
    }

    /** @param list<string> $styleIds */
    private function seedAi(
        string $query,
        AiSearchOutcome $outcome,
        string $when,
        ?int $type = null,
        ?Instrument $instrument = null,
        array $styleIds = [],
        ?float $latitude = null,
        ?float $longitude = null,
    ): MusicianSearchLog {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $log = new MusicianSearchLog(MusicianSearchKind::Ai, hash('sha256', 'ai'), new \DateTimeImmutable($when));
        $log->aiQuery = $query;
        $log->aiOutcome = $outcome;
        $log->type = $type;
        $log->instrument = $instrument instanceof Instrument ? $entityManager->getReference(Instrument::class, $instrument->id) : null;
        $log->styleIds = $styleIds;
        $log->latitude = $latitude;
        $log->longitude = $longitude;
        $entityManager->persist($log);
        $entityManager->flush();

        return $log;
    }
}
