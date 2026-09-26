<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\AiSearchOutcome;
use App\Enum\Search\MusicianSearchKind;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use App\Repository\Search\MusicianSearchLogRepository;
use App\Service\Finder\Musician\Builder\AnnounceMusicianFilterBuilder;
use App\Service\Finder\Musician\MusicianFilterGenerator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Which musician searches are kept, and what of them (#1075). The answers themselves are covered by
 * the search tests; these read the rows the searches left.
 */
#[ResetDatabase]
class MusicianSearchRecordingTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_filters_search_is_recorded_with_its_criteria(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $this->client->request('GET', '/api/musicians/search?' . http_build_query([
            'type' => '2',
            'instrument' => $drum->id,
            'styles' => [$rock->id],
            'latitude' => '50.85',
            'longitude' => '4.35',
            'location' => ' Bruxelles ',
        ]));

        $this->assertResponseIsSuccessful();
        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertSame(MusicianSearchKind::Filters, $log->kind);
        $this->assertSame(2, $log->type);
        $this->assertSame((string) $drum->id, (string) $log->instrument?->id);
        $this->assertSame([(string) $rock->id], $log->styleIds);
        $this->assertSame('Bruxelles', $log->locationName);
        $this->assertSame(50.85, $log->latitude);
        $this->assertSame(4.35, $log->longitude);
        $this->assertSame(0, $log->firstPageResultCount);
        $this->assertFalse($log->authenticated);
        $this->assertNull($log->aiQuery);
        $this->assertSame(64, strlen($log->visitorHash));
    }

    public function test_a_city_name_without_coordinates_is_not_kept(): void
    {
        $this->client->request('GET', '/api/musicians/search?type=1&location=Bruxelles');

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->logs()[0]->locationName);
    }

    public function test_a_logged_in_search_says_so_and_nothing_more(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/musicians/search?type=1');

        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->logs()[0]->authenticated);
    }

    public function test_the_next_pages_of_a_search_are_not_recorded_again(): void
    {
        $this->client->request('GET', '/api/musicians/search?type=2&page=2');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->logs());
    }

    public function test_the_search_page_opening_list_is_not_a_search(): void
    {
        $this->client->request('GET', '/api/musicians/search');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->logs());
    }

    public function test_a_crawler_is_not_recorded(): void
    {
        $this->client->request('GET', '/api/musicians/search?type=2', server: [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->logs());
    }

    public function test_a_landing_page_list_is_not_a_search(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();

        $this->client->request('GET', '/api/musicians/search?landing=1&instrument=' . $drum->id);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->logs());
    }

    public function test_past_its_budget_a_visitor_still_gets_results_without_being_recorded(): void
    {
        self::getContainer()->get('limiter.search_log')->create('127.0.0.1')->consume(60);

        $this->client->request('GET', '/api/musicians/search?type=2');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->logs());
    }

    public function test_a_search_that_cannot_be_recorded_still_answers_with_its_results(): void
    {
        // The results carry their styles, loaded after the recording: a failed write that closed the
        // entity manager would turn this into a 500.
        $rock = StyleFactory::new()->asRock()->create();
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'batteuse', 'email' => 'batteuse@test.com']);
        $drum = InstrumentFactory::new()->asDrum()->create();
        $announce = MusicianAnnounceFactory::new()->asMusician()->withInstrument($drum)->withStyles([$rock])->create([
            'author' => $author,
            'locationName' => 'Mons',
            'note' => 'Cherche un groupe',
        ]);
        $failingRepository = $this->createStub(MusicianSearchLogRepository::class);
        $failingRepository->method('insert')->willThrowException(new \RuntimeException('Lost the connection'));
        self::getContainer()->set(MusicianSearchLogRepository::class, $failingRepository);

        $this->client->request('GET', '/api/musicians/search?type=1');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMusician',
            '@id' => '/api/musicians/search',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [
                [
                    '@id' => '/api/announce_musicians/' . $announce->id,
                    '@type' => 'AnnounceMusician',
                    'id' => (string) $announce->id,
                    'location_name' => 'Mons',
                    'note' => 'Cherche un groupe',
                    'user' => [
                        '@type' => 'User',
                        'id' => (string) $author->id,
                        'username' => 'batteuse',
                        'has_musician_profile' => false,
                    ],
                    'instrument' => ['@type' => 'Instrument', 'name' => 'Batteur'],
                    'type' => 1,
                    'styles' => [['@type' => 'Style', 'name' => 'Rock']],
                ],
            ],
            'view' => ['@id' => '/api/musicians/search?type=1', '@type' => 'PartialCollectionView'],
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

    public function test_an_ai_search_is_recorded_with_the_filters_it_produced(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $this->answerWith([
            'type' => 1,
            'instrument' => (string) $drum->id,
            'styles' => [(string) $rock->id],
            'coordinates' => ['latitude' => 48.85, 'longitude' => 2.35],
        ]);

        $this->client->request('GET', '/api/musicians/filters?search=' . urlencode('Je cherche un batteur rock à Paris'));

        $this->assertResponseIsSuccessful();
        $log = $this->logs()[0];
        $this->assertSame(MusicianSearchKind::Ai, $log->kind);
        $this->assertSame('Je cherche un batteur rock à Paris', $log->aiQuery);
        $this->assertSame(AiSearchOutcome::Filters, $log->aiOutcome);
        $this->assertSame(1, $log->type);
        $this->assertSame((string) $drum->id, (string) $log->instrument?->id);
        $this->assertSame([(string) $rock->id], $log->styleIds);
        $this->assertSame(48.85, $log->latitude);
        $this->assertNull($log->locationName);
        $this->assertNull($log->firstPageResultCount);
    }

    public function test_an_ai_search_that_understood_nothing_is_recorded_as_such(): void
    {
        $this->answerWith(['styles' => [], 'coordinates' => null]);

        $this->client->request('GET', '/api/musicians/filters?search=' . urlencode('Bonjour, comment allez-vous ?'));

        $log = $this->logs()[0];
        $this->assertSame(AiSearchOutcome::Nothing, $log->aiOutcome);
        $this->assertNull($log->type);
        $this->assertNull($log->instrument);
    }

    public function test_an_ai_search_that_failed_is_recorded_as_such(): void
    {
        $this->answerWith('not an object');

        $this->client->request('GET', '/api/musicians/filters?search=' . urlencode('Je cherche un groupe de jazz'));

        $this->assertResponseStatusCodeSame(404);
        $log = $this->logs()[0];
        $this->assertSame(AiSearchOutcome::Failed, $log->aiOutcome);
        $this->assertSame('Je cherche un groupe de jazz', $log->aiQuery);
    }

    /**
     * The model answers from memory: the real platform refuses to be built without an API key.
     *
     * @param array<string, mixed>|string $answer
     */
    private function answerWith(array|string $answer): void
    {
        $container = self::getContainer();
        $container->set(MusicianFilterGenerator::class, new MusicianFilterGenerator(
            $container->get(InstrumentRepository::class),
            $container->get(StyleRepository::class),
            new Agent(
                new InMemoryPlatform(static fn (): ResultInterface => is_array($answer) ? new ObjectResult($answer) : new TextResult($answer)),
                'gpt-4o-mini',
            ),
            $container->get(AnnounceMusicianFilterBuilder::class),
        ));
    }

    /** @return list<MusicianSearchLog> */
    private function logs(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return array_values($entityManager->getRepository(MusicianSearchLog::class)->findAll());
    }
}
