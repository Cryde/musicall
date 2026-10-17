<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Entity\Forum\ForumTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;

use function Zenstruck\Foundry\Persistence\delete;

/**
 * The search matches in the forums of the page's locale only (#1197). MATCH AGAINST only sees committed rows, so
 * this class turns DAMA's per-test transaction off, and each test deletes what it committed before asserting.
 *
 * No #[ResetDatabase] on purpose: with DAMA off, Foundry drops the schema before each test, before DAMA has rolled
 * back the previous class's transaction, whose table locks then block the drop forever. Foundry skips the first
 * of those resets in a run, which is all PublicationSearchCollectionTest gets away with, so a second class using
 * one would hang the suite.
 */
class ForumSearchLocaleTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public static function setUpBeforeClass(): void
    {
        StaticDriver::setKeepStaticConnections(false);
    }

    public static function tearDownAfterClass(): void
    {
        StaticDriver::setKeepStaticConnections(true);
    }

    public function test_a_french_page_finds_the_french_topics_only(): void
    {
        [$frenchTopic] = $topics = $this->createFrenchAndEnglishTopics();
        $ids = $this->idsOf($frenchTopic);

        $this->client->request('GET', '/api/forums/search?term=Marshall');
        $this->deleteTopics($topics);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumSearchResult',
            '@id' => '/api/forums/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/forum_search_results/' . $ids['topic'],
                    '@type' => 'ForumSearchResult',
                    'topic_id' => $ids['topic'],
                    'topic_slug' => 'quel-ampli-marshall-choisir',
                    'topic_title' => 'Quel ampli Marshall choisir',
                    'topic_post_number' => 0,
                    'last_post_datetime' => null,
                    'forum_id' => $ids['forum'],
                    'forum_title' => 'Matériel',
                    'forum_slug' => 'materiel',
                    'category_id' => $ids['category'],
                    'category_title' => 'Généralités',
                    'post_snippet' => null,
                    'post_id' => null,
                    'match_type' => 'title',
                    'term' => 'Marshall',
                ],
            ],
            'view' => [
                '@id' => '/api/forums/search?term=Marshall',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_an_english_page_finds_the_english_topics_only(): void
    {
        [, $englishTopic] = $topics = $this->createFrenchAndEnglishTopics();
        $ids = $this->idsOf($englishTopic);

        $this->client->request('GET', '/api/forums/search?term=Marshall', server: ['HTTP_X_LOCALE' => 'en']);
        $this->deleteTopics($topics);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumSearchResult',
            '@id' => '/api/forums/search',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/forum_search_results/' . $ids['topic'],
                    '@type' => 'ForumSearchResult',
                    'topic_id' => $ids['topic'],
                    'topic_slug' => 'which-marshall-amp-to-buy',
                    'topic_title' => 'Which Marshall amp to buy',
                    'topic_post_number' => 0,
                    'last_post_datetime' => null,
                    'forum_id' => $ids['forum'],
                    'forum_title' => 'Gear',
                    'forum_slug' => 'gear',
                    'category_id' => $ids['category'],
                    'category_title' => 'General',
                    'post_snippet' => null,
                    'post_id' => null,
                    'match_type' => 'title',
                    'term' => 'Marshall',
                ],
            ],
            'view' => [
                '@id' => '/api/forums/search?term=Marshall',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * The same word in a French and an English topic title, so only the locale tells them apart.
     *
     * @return array{ForumTopic, ForumTopic}
     */
    private function createFrenchAndEnglishTopics(): array
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $frenchCategory = ForumCategoryFactory::new(['position' => 1, 'title' => 'Généralités', 'locale' => 'fr'])->create();
        $englishCategory = ForumCategoryFactory::new(['position' => 1, 'title' => 'General', 'locale' => 'en'])->create();
        $frenchForum = ForumFactory::new(['forumCategory' => $frenchCategory, 'title' => 'Matériel', 'slug' => 'materiel'])->create();
        $englishForum = ForumFactory::new(['forumCategory' => $englishCategory, 'title' => 'Gear', 'slug' => 'gear'])->create();

        return [
            ForumTopicFactory::new([
                'forum' => $frenchForum,
                'author' => $author,
                'title' => 'Quel ampli Marshall choisir',
                'slug' => 'quel-ampli-marshall-choisir',
                'postNumber' => 0,
                'creationDatetime' => new \DateTime('2026-09-20 10:00:00'),
            ])->create(),
            ForumTopicFactory::new([
                'forum' => $englishForum,
                'author' => $author,
                'title' => 'Which Marshall amp to buy',
                'slug' => 'which-marshall-amp-to-buy',
                'postNumber' => 0,
                'creationDatetime' => new \DateTime('2026-09-25 10:00:00'),
            ])->create(),
        ];
    }

    /**
     * Read before the rows are deleted.
     *
     * @return array{topic: string, forum: string, category: string}
     */
    private function idsOf(ForumTopic $topic): array
    {
        return [
            'topic' => (string) $topic->id,
            'forum' => (string) $topic->forum->id,
            'category' => (string) $topic->forum->forumCategory->id,
        ];
    }

    /**
     * Committed rows outlive the test, and the next class runs inside DAMA's transaction on top of them.
     *
     * @param ForumTopic[] $topics
     */
    private function deleteTopics(array $topics): void
    {
        $author = $topics[0]->author;
        foreach ($topics as $topic) {
            $forum = $topic->forum;
            $category = $forum->forumCategory;
            $source = $category->forumSource;
            delete($topic);
            delete($forum);
            delete($category);
            delete($source);
        }
        delete($author);
    }
}
