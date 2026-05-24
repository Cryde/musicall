<?php declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Covers the input-validation surface of /api/forums/search.
 *
 * The happy-path cases (MATCH AGAINST returning hits) cannot run here:
 * InnoDB's FULLTEXT auxiliary tables are not updated until the transaction
 * that inserted the rows commits, and DAMA wraps each test in an
 * always-rolled-back transaction. Inserts seeded inside a test are therefore
 * invisible to MATCH AGAINST in the same test.
 *
 * Disabling DAMA via StaticDriver::setKeepStaticConnections(false) in
 * setUpBeforeClass works for this class but leaks state into the rest of
 * the suite (subsequent tests start without transaction wrapping and pay
 * a full schema reset per test, ballooning suite runtime ~25x). So the
 * happy paths are covered by manual exercise in dev and by the
 * PostSnippetExtractorTest unit tests for the snippet extraction layer.
 */
#[ResetDatabase]
class ForumSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_search_without_term_returns_422(): void
    {
        $this->client->request('GET', '/api/forums/search');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Le terme de recherche est requis',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Le terme de recherche est requis',
        ]);
    }

    public function test_search_with_short_term_returns_422(): void
    {
        $this->client->request('GET', '/api/forums/search?term=ab');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Le terme de recherche doit contenir au moins 3 caractères',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Le terme de recherche doit contenir au moins 3 caractères',
        ]);
    }

    public function test_search_with_no_matches_returns_empty_collection(): void
    {
        $category = ForumCategoryFactory::createOne(['title' => 'Instruments']);
        $forum = ForumFactory::createOne(['forumCategory' => $category, 'slug' => 'guitares', 'title' => 'Guitares']);
        ForumTopicFactory::createOne([
            'forum' => $forum,
            'title' => 'Question rapide',
            'slug' => 'question-rapide-no-match',
            'postNumber' => 1,
        ]);

        $this->client->request('GET', '/api/forums/search?term=xylophonexyz');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['totalItems']);
        $this->assertCount(0, $data['member']);
    }

    public function test_search_is_publicly_accessible(): void
    {
        // No loginUser() call - PUBLIC_ACCESS lets the route render even with
        // no matches. Confirms the security guard does not reject anonymous.
        $this->client->request('GET', '/api/forums/search?term=anythinguniquezzz');

        $this->assertResponseIsSuccessful();
    }
}
